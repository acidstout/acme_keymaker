<?php
/*************************************************************
*
*	ACME Products Keymaker
*	Generates license information file for ACME products.
*
*	Uses simple XOR encryption and SHA-1 hashing.
*	
*	@author: Nils Rekow
*
*************************************************************/

//----------------------------------------------------
// Configuration
//----------------------------------------------------
$prod_title      = 'ACME Products Keymaker';					// Name of the keymaker. Change at will.
$prod_about      = 'Where children sing songs of binary bliss'; // Company of the product or of the keymaker. You decide.
$prod_link       = 'https://rekow.ch';							// See above.
$lic_num_random  = false;										// By default generate a first license number if none is given and keep that during license generation.
$lic_sep         = ';';											// Separater character. Used to separate base64 encoded strings from each other in the encrypted payload.
$lic_type_txt    = array(
		0 => 'Evaluation',
		1 => 'Basic',
		2 => 'Extended',
		3 => 'Premium',
		4 => 'Corporate',
		99 => 'Internal');										// License types. Extend at will.
//----------------------------------------------------

		

/**
 * Simply XOR encryption/decryption class
 *
 * Encrypted strings will get base64 encoded in order to not break stuff.
 *
 * Uses random initialization vector.
 *
 */
class xorCrypt {
	private $password = NULL;

	/**
	 * Sets password to use for encryption/decryption
	 *
	 * @param string $password
	 */
	public function __construct($password) {
		$this->password = $password;
		return $this;
	}
	
	
	/**
	 * Generates a random Initialization vector
	 *
	 * @param integer $length
	 * @return string $iv
	 */
	private function _getRandomIV($length) {
		$iv = '';
		while($length-- > 0) {
			$iv .= chr(mt_rand() & 0xff);
		}
		return $iv;
	}
	
	
	/**
	 * Encrypts and encodes given payload and returns result.
	 *
	 * @param string $plain_text
	 * @param integer $iv_length - langth of IV
	 * @return string $enc_text - base64 encoded and XOR encrypted payload
	 */
	public function encrypt($plain_text, $iv_length = 16) {
		$plain_text .= "\x13";
		$n = strlen($plain_text);
		
		if ($n % 16) {
			$plain_text .= str_repeat("\0", 16 - ($n % 16));
			$i = 0;
			$enc_text = $this->_getRandomIV($iv_length);
			$iv = substr($this->password ^ $enc_text, 0, 512);
			
			while ($i < $n) {
				$block = substr($plain_text, $i, 16) ^ pack('H*', sha1($iv));
				$enc_text .= $block;
				$iv = substr($block . $iv, 0, 512) ^ $this->password;
				$i += 16;
			}
			
			return base64_encode($enc_text);
		}
		
		return false;
	}
	
	/**
	 * Decodes and decrypts given payload and returns result.
	 *
	 * @param string $enc_text - base64 encoded and XOR encrypted payload
	 * @param integer $iv_length - length of IV
	 * @return string $plain_text - decrypted and decoded
	 */
	public function decrypt($enc_text, $iv_length = 16) {
		$enc_text = base64_decode($enc_text);
		$plain_text = '';
		$iv = substr($this->password ^ substr($enc_text, 0, $iv_length), 0, 512);
		$n = strlen($enc_text);
		$i = $iv_length;
		
		while ($i < $n) {
			$block = substr($enc_text, $i, 16);
			$plain_text .= $block ^ pack('H*', sha1($iv));
			$iv = substr($block . $iv, 0, 512) ^ $this->password;
			$i += 16;
		}
		
		return stripslashes(preg_replace('/\\x13\\x00*$/', '', $plain_text));
	}
}


/**
 * Generate a random UUID.
 *
 * @return string $uuid
 */
function uuid() {
	// The field names refer to RFC 4122 section 4.1.2
	$uuid = sprintf('%04x%04x-%04x-%03x4-%04x-%04x%04x%04x',
			mt_rand(0, 65535), mt_rand(0, 65535), // 32 bits for "time_low"
			mt_rand(0, 65535), // 16 bits for "time_mid"
			mt_rand(0, 4095),  // 12 bits before the 0100 of (version) 4 for "time_hi_and_version"
			bindec(substr_replace(sprintf('%016b', mt_rand(0, 65535)), '01', 6, 2)),
			// 8 bits, the last two of which (positions 6 and 7) are 01, for "clk_seq_hi_res"
			// (hence, the 2nd hex digit after the 3rd hyphen can only be 1, 5, 9 or d)
			// 8 bits for "clk_seq_low"
			mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535) // 48 bits for "node"
			);
	
	return strtoupper($uuid);
}


/**
 * Checks if an encrypted payload contains valid license information.
 *
 * @param string $lines
 * @return array $license
 */
function checkLicense($xorCrypt, $lines) {
	$license = array(
			'info' => '',
			'guid' => '',
			'type' => '0',
			'num' => '0'
	);
	
	if (isset($lines) && !empty($lines)) {
		if (count($lines) >= 4) {
			$license['info'] = $lines[0];
			$license['guid'] = $lines[1];
			$license['type'] = $lines[2];
			$license['num']  = $lines[3];
		}
	}
	
	if (!empty($license['info']) && !empty($license['guid']) && !empty($license['type']) && !empty($license['num'])) {
		$license['info'] = $xorCrypt->decrypt($license['info']);
		$license['guid'] = $xorCrypt->decrypt($license['guid']);
		$license['type'] = $xorCrypt->decrypt($license['type']);
		$license['num']  = $xorCrypt->decrypt($license['num']);
	}
	
	return $license;
}


/**
 * Prepares HTML option-tags for number of licensed devices select-box.
 * 
 * @return string
 */
function generateMachineLicenseOptions($lic_machines) {
	$machine_licenses = '';
	$x = 0;
	
	for ($i = 0; $i < 1000; $i += $x) {
		$selected = '';
		if ($i == $lic_machines) {
			$selected = ' selected';
		}
		
		$machine_licenses .= '<option value="' . $i . '"' . $selected . '>' . $i .'</option>';
		
		if ($i < 10) {
			$i++;
		}
		
		if ($i >= 10) {
			$x = 50;
			if ($i < 100) {
				$x = 5;
			}
		}
	}

	$selected = '';
	if ($lic_machines == -1) {
		$selected = ' selected';
	}
	
	$machine_licenses .= '<option value="-1"' . $selected . '>unlimited</option>';
	
	return $machine_licenses;
}


/**
 * Prepares HTML option-tags for license types.
 */
function generateLicenseTypes($lic_type_txt, $lic_type) {
	$license_types = '';
	foreach ($lic_type_txt as $key => $value) {
		$selected = '';
		
		if ($key == $lic_type) {
			$selected = ' selected';
		}

		$license_types .= '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
	}

	return $license_types;
}


// Create a new XOR object with the key "Secret".
$xorCrypt = new xorCrypt('Secret');

// Check POSTs
if (isset($_POST['lic_do']) && isset($_POST['lic_txt']) && !empty($_POST['lic_txt'])) {
	// Generate a license from the provided information.
	$lic_txt = $_POST['lic_txt']; 
	$lic_type = $_POST['lic_type']; 
	$lic_machines = $_POST['lic_machines'];
	
	if (isset($_POST['lic_num_random']) && !empty($_POST['lic_num_random'])) {
		// Generate a random license number.
		$lic_num = uuid();
		$lic_num_random = true;
	} else {
		if (isset($_POST['lic_num']) && !empty($_POST['lic_num'])) {
			// Use the provided license number (without validation).
			$lic_num = $_POST['lic_num'];
		} else {
			// Generate a unique license number from the provided license information.
			$md5 = strtoupper(md5($lic_txt));
			$lic_num = substr($md5, 0, 8) . '-' . substr($md5, 8, 4) . '-' . substr($md5, 12, 4) . '-' . substr($md5, 16, 4) . '-' . substr($md5, 20, 12);
		}
	}
	
	// Encrypt license information using simple XOR and Base64 encode the result.
	$enc_lic_txt = $xorCrypt->encrypt($lic_txt);
	$enc_lic_type = $xorCrypt->encrypt($lic_type);
	$enc_lic_machines = $xorCrypt->encrypt($lic_machines);
	$enc_lic_num = $xorCrypt->encrypt($lic_num);
} else {
	// Fallback if nothing was provided (e.g. keymaker was just called by a GET request).
	$lic_info = "MSxajxWNuNFpkxP5XeYhk/gLD6l5Z62zo5UkoV5aDJr3MtYx7L4hswanGe6RHUt7nSBCAC7PVUAI76OgjT4Smw3Z8DER85Cdh7xksuiHDNo5vGQbZvgbgSsXPajzoAuM4lwNdqNAarog1X7tenbUsA==" . $lic_sep
		. "dMWam0+GoyfQZwS7VosrfYwwLTXaXYJoMScIJqIUAEYiWLeTTSTDY/xB40MaoqZODsGo69kALgdFtszgM/pOAA==" . $lic_sep
		. "FP4gj9eZPjY2WZGeFqyilHI6nvrrVdaifxlFVng5WjM=" . $lic_sep
		. "pQ3hq280FCGcQ+P2NnerR53w2ws38Nu36WZGc+FE3zU=";
	
	// Check if encrypted license has been provided.
	if (isset($_POST['lic_info']) && isset($_POST['lic_re'])) {
		$lic_info = $_POST['lic_info'];
	}
	
	$lic_result = array();
	$s = '';
	
	// Combine parts into array.
	for ($i = 0; $i <= strlen($lic_info); $i++) {
		if (isset($lic_info[$i])) {
			if ($lic_info[$i] != $lic_sep) {
				$s .= $lic_info[$i];
			} else {
				$lic_result[] = $s;
				$s = '';
			}
		}  
	}
	
	$lic_result[] = $s;

	// Decrypt array and split result into readable parts.
	$license = checkLicense($xorCrypt, $lic_result);
	$lic_num = $license['guid'];
	$lic_txt = $license['info'];
	$lic_type = $license['type'];
	$lic_machines = $license['num'];
}


// Save license into temporary file and offer download.
if ((isset($_POST['lic_save'])) && (isset($_POST['lic_info'])) && (!empty($_POST['lic_info']))) {
	$lic_info = $_POST['lic_info'];

	// Proper way to determine the temporary files folder. Requires at least PHP 5.3 to work.
	$upload_tmp_dir = ini_get('upload_tmp_dir') ?: sys_get_temp_dir();
	
	$local_file = $upload_tmp_dir . DIRECTORY_SEPARATOR . md5(gmdate('D, d M Y H:i:s', time() + 24 * 60 * 60) . ' GMT') . '.lic';
	
	$download_file = 'acme.lic';
	$fp = fopen($local_file, 'w');
	
	if ($fp) {
		fwrite($fp, $lic_info);
		fclose($fp);

		header('Cache-control: private');
		header('Content-Type: application/octet-stream');
		header('Content-Length: ' . filesize($local_file));
		header('Content-Disposition: filename=' . $download_file);
		flush();
		ob_end_clean();

		$file = fopen($local_file, 'r');
		
		while (!feof($file)) {
			echo fread($file, filesize($local_file));
			flush();
			sleep(1);
		}
		
		fclose($file);
		unlink($local_file);
	} else {
		$messages[] = 'Cannot write to ' . $local_file;
		print_r($messages);
	}
}


// Generate select-box options.
$machine_licenses = generateMachineLicenseOptions($lic_machines);
$license_types = generateLicenseTypes($lic_type_txt, $lic_type);


// Prepare license information.
$license_information = '';
if (!empty($enc_lic_txt) && !empty($enc_lic_num) && !empty($enc_lic_type) && !empty($enc_lic_machines)) {
	$license_information =  $enc_lic_txt . $lic_sep . $enc_lic_num . $lic_sep . $enc_lic_type . $lic_sep . $enc_lic_machines;
} else {
	if (isset($lic_info)) {
		$license_information = $lic_info;
	}
}


// Decide if we need to check the "[] Random?" checkbox.
$license_random_number_checked = '';
if ($lic_num_random === true) {
	$license_random_number_checked = 'checked';
}


//
// HTML template
//
?><!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title><?php echo $prod_title;?></title>
		<style type="text/css">
			/* START: reset style */
			html, body, div, span, applet, object, iframe,
			h1, h2, h3, h4, h5, h6, p, blockquote, pre,
			a, abbr, acronym, address, big, cite, code,
			del, dfn, em, img, ins, kbd, q, s, samp,
			small, strike, strong, sub, sup, tt, var,
			b, u, i, center,
			dl, dt, dd, ol, ul, li,
			fieldset, form, label, legend,
			table, caption, tbody, tfoot, thead, tr, th, td,
			article, aside, canvas, details, embed, 
			figure, figcaption, footer, header, hgroup, 
			menu, nav, output, ruby, section, summary,
			time, mark, audio, video {
				border: 0;
				font-size: 100%;
				font: inherit;
				margin: 0;
				padding: 0;
				vertical-align: baseline;
			}

			/* HTML5 display-role reset for older browsers */
			article, aside, details, figcaption, figure, 
			footer, header, hgroup, menu, nav, section {
				display: block;
			}

			body {
				line-height: 14pt;
			}

			ol, ul {
				list-style: none;
			}

			blockquote, q {
				quotes: none;
			}

				blockquote:before,
				blockquote:after,
				q:before,
				q:after {
					content: '';
					content: none;
				}

			table {
				border-spacing: 0;
			}

				td {
					vertical-align: middle;
				}


			/* START: keymaker style */
			body {
				background-color: #555;
				font-family: 'Segoe UI', sans-serif;
			}

			button, input[type=checkbox], label, select {
				cursor: pointer;
				font-family: 'Segoe UI', sans-serif;
			}

				button {
					font-family: 'Segoe UI', sans-serif;
					font-weight: 600;
					height: 28px;
					width: 116px;
				}

					button[disabled] {
						cursor: default;
					}

				input[type=checkbox] {
					margin-right: 5px;
					position: relative;
					top: 2px;
				}

				label {
					font-size: 8pt;
				}

				select {
					text-align: left;
					width: 210px;
				}

			input[type="text"] {
				font-family: 'Consolas', monospace;
				padding: 0 3px;
				text-align: center;
				width: 385px;
			}

			textarea {
				font-size: 10pt;
				height: 150px;
				line-height: 14pt;
				margin: 0;
				padding: 3px;
				resize: none;
			}

				textarea[name="lic_txt"] {
					font-family: 'Segoe UI', sans-serif;
					text-align: center;
					width: 300px;
				}

				textarea[name="lic_info"] {
					font-family: 'Consolas', monospace;
					width: 470px;
				}


			table tr {
				height: 24px;
			}

				table tr td:first-child {
					overflow: hidden;
					text-overflow: ellipsis;
					width: 98px;
				}

				
			/* classes */
			.floatleft {
				float: left;
			}

			.floatright {
				float: right;
			}

			.clear {
				clear: both;
			}


			/* ids */
			#box {
				background-color: #ccc;
				border: 1px solid #777;
				border-radius: 12px;
				padding: 15px 15px 25px 15px;
				position: absolute;
				top: 50%;
				left: 50%;
				transform: translate(-50%,-50%);
				height: 220px;
				width: 820px;
			}

				#header {
					font-size: 16pt;
					font-weight: bold;
					padding: 0 0 8px 2px;
					position: relative;
					top: -2px;
					text-shadow: 0 0 4px #aaa;
				}

				#about {
					color: #555;
					cursor: default;
					font-size: 7pt;
					line-height: 8pt;
					position: absolute;
					top: 10px;
					right: 15px;
					text-align: right;
					text-shadow: 0 0 2px #aaa;
				}

					#about a {
						color: #555 !important;
					}
		</style>
	</head>
	<body>
		<div id="box">
			<div id="header"><?php echo $prod_title;?></div>
			<div id="about"><?php echo $prod_about;?><br/><a href="<?php echo $prod_link;?>" target="_blank"><?php echo parse_url($prod_link, PHP_URL_HOST);?></a></div>
			<form action="#" method="post">
				<textarea autofocus class="floatleft" name="lic_txt" placeholder="Enter licensee ..."><?php echo $lic_txt;?></textarea>
				<textarea class="floatright" name="lic_info" placeholder="No license information created, yet. Paste an existing license into this box and click Check to show the licensee."><?php echo $license_information;?></textarea>
				<br class="clear"/>
				<div class="floatleft">
					<table>
						<tr>
							<td><label for="lic_type">License type:</label></td>
							<td><select id="lic_type" name="lic_type" title="Type of license."><?php echo $license_types;?></select></td>
						</tr>
						<tr>
							<td><label for="lic_machines">Licensed devices:</label></td>
							<td><select id="lic_machines" name="lic_machines" title="Number of licensed machines."><?php echo $machine_licenses;?></select></td>
						</tr>
					</table>
				</div>
				<div class="floatright">
					<input type="text" name="lic_num" value="<?php echo $lic_num;?>" placeholder="No license number"/>
					<span  title="Generate a random license number instead of a unique one from the license information."><input type="checkbox" id="lic_num_random" name="lic_num_random" <?php echo $license_random_number_checked;?>/><label for="lic_num_random">Random?</label></span>
					<br/>
					<button type="reset" name="reset" title="Undo any changes.">Reset</button>
					<button type="submit" name="lic_re" title="Check if encrypted license information is valid.">Check</button>
					<button type="submit" name="lic_do" title="Create encrypted license information from provided information.">Generate</button>
					<button type="submit" name="lic_save" title="Download encrypted license information as keyfile." <?php if(!isset($_POST['lic_txt']) || empty($_POST['lic_txt']) || empty($lic_txt)) {?>disabled<?php }?>>Save as ...</button>
				</div>
			</form>
		</div>
	</body>
</html>