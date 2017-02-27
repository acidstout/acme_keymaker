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


// Configuration
$xorCrypt        = new xorCrypt();
$xorCrypt->setKey('Secret');
$prod_title      = 'ACME Products Keymaker';
$prod_about      = 'Das sch&ouml;ne System';
$prod_link       = 'http://www.das-schoene-system.de';
$lic_type_txt    = array( 0 => 'Evaluation', 1 => 'Basic', 2 => 'Extended', 3 => 'Premium', 4 => 'Corporate', 99 => 'Internal');
$lic_num_random  = false;
$lic_sep         = ';';


if (isset($_POST['lic_do']) && isset($_POST['lic_txt']) && !empty($_POST['lic_txt'])) {
	// Generate a license from the provided information.
	$lic_txt          = $_POST['lic_txt']; 
	$lic_type         = $_POST['lic_type']; 
	$lic_machines     = $_POST['lic_machines'];
	
	if (isset($_POST['lic_num_random']) && !empty($_POST['lic_num_random'])) {
		// Generate a random license number.
		$lic_num        = uuid();
		$lic_num_random = true;
	} else {
		if (isset($_POST['lic_num']) && !empty($_POST['lic_num'])) {
			// Use the provided license number (without validation).
			$lic_num = $_POST['lic_num'];
		} else {
			// Generate a unique license number from the provided license information.
			$md5     = strtoupper(md5($lic_txt));
			$lic_num = substr($md5, 0, 8) . '-' . substr($md5, 8, 4) . '-' . substr($md5, 12, 4) . '-' . substr($md5, 16, 4) . '-' . substr($md5, 20, 12);
		}
	}
	
	// Encrypt license information using simple XOR and Base64 encode the result.
	$enc_lic_txt      = $xorCrypt->encrypt($lic_txt);
	$enc_lic_type     = $xorCrypt->encrypt($lic_type);
	$enc_lic_machines = $xorCrypt->encrypt($lic_machines);
	$enc_lic_num      = $xorCrypt->encrypt($lic_num);
} else {
	// Check if encrypted license has been provided.
	if (isset($_POST['lic_info']) && isset($_POST['lic_re'])) {
		$lic_info   = $_POST['lic_info'];
	} else {
		// Fallback if nothing was provided (e.g. keymaker was just called by a GET request).
		$lic_info = "MSxajxWNuNFpkxP5XeYhk/gLD6l5Z62zo5UkoV5aDJr3MtYx7L4hswanGe6RHUt7nSBCAC7PVUAI76OgjT4Smw3Z8DER85Cdh7xksuiHDNo5vGQbZvgbgSsXPajzoAuM4lwNdqNAarog1X7tenbUsA==;"
		          . "dMWam0+GoyfQZwS7VosrfYwwLTXaXYJoMScIJqIUAEYiWLeTTSTDY/xB40MaoqZODsGo69kALgdFtszgM/pOAA==;"
		          . "FP4gj9eZPjY2WZGeFqyilHI6nvrrVdaifxlFVng5WjM=;"
		          . "pQ3hq280FCGcQ+P2NnerR53w2ws38Nu36WZGc+FE3zU=";
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
	$license      = checkLicense($lic_result);
	$lic_num      = $license['guid'];
	$lic_txt      = $license['info'];
	$lic_type     = $license['type'];
	$lic_machines = $license['num'];
}


if ((isset($_POST['lic_save'])) && (isset($_POST['lic_info'])) && (!empty($_POST['lic_info']))) {
	$lic_info = $_POST['lic_info'];

	$local_file = '/tmp/' . md5(gmdate('D, d M Y H:i:s', time() + 24 * 60 * 60) . ' GMT') . '.lic';
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
			print fread($file, filesize($local_file));
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

?>
<!DOCTYPE html>
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
		<title><?php print $prod_title;?></title>
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
				margin: 0;
				padding: 0;
				border: 0;
				font-size: 0.5em;
				font: inherit;
				vertical-align: baseline;
			}
			
			td {
				vertical-align: middle;
				font-size: 0.5em;
				text-shadow: 1px 1px 10px #888;
			}
			/* HTML5 display-role reset for older browsers */
			article, aside, details, figcaption, figure, 
			footer, header, hgroup, menu, nav, section {
				display: block;
			}
			body {
				line-height: 16px;
			}
			ol, ul {
				list-style: none;
			}
			blockquote, q {
				quotes: none;
			}
			blockquote:before, blockquote:after,
			q:before, q:after {
				content: '';
				content: none;
			}
			table {
				border-spacing: 0;
			}
			/* END: reset style */
			
			/* START: keymaker style */
			* { font-family:sans-serif !important; font-size: 1em; text-overflow: ellipsis; }
			body { background:#555; }
			button { width:116px; height:28px; cursor:pointer; }
			button[disabled] { cursor:default; }
			select { cursor:pointer; width:210px; }
			input[type=checkbox] { cursor:pointer; position:relative; top:2px; margin-right: 5px; }
			label { cursor:pointer; }
			table tr { height:24px; }
			textarea { font-family:sans-serif; font-size:0.5em; line-height:1.3em; padding:3px; margin:0; resize:none; }
			/* classes */
			.rightmargin { margin: 6px 16px 0 0; }
			.lic { width:300px; height:100px; min-width:300px; min-height:100px; max-width:300px; max-height:100px; text-align:center; }
			.mono { margin:0 0 0 8px; width:460px; height:100px; min-width:460px; min-height:100px; max-width:460px; max-height:100px; font-family:monospace; font-size:0.5em; }
			.alignleft { text-align:left; }
			.alignright { text-align:right; }
			.floatleft { float:left; }
			.floatright { float:right; }
			.clear { clear:both; }
			/* ids */
			#box { position:absolute; top:50%; left:50%; margin-top:-150px; margin-left:-400px;width:820px; height:175px; padding:15px 15px 25px 15px; background:#ccc; border:1px solid #777; border-radius: 12px; font-size:0.5em; }
			#lic_num { font-family:monospace; font-size:0.5em; width:385px; padding:0 3px; text-align:center; }
			#header { position:relative; top:-2px; padding:0 0 8px 2px; font-size: 2.4em; font-weight:bold; text-shadow: 1px 1px 5px #888; }
			#about { position:absolute; top:10px; right:15px; text-align:right; color:#888;  line-height:1.2em; cursor:default; text-shadow: 1px 1px 5px #aaa; }
			#about a { color:#888 !important; }
			/* END: keymaker style */
		</style>
	</head>
	<body>
		<div id="box">
			<div id="header"><?php print $prod_title;?></div>
			<div id="about"><?php print $prod_about;?><br/><a href="<?php print $prod_link;?>" target="_blank"><?php print parse_url($prod_link, PHP_URL_HOST);?></a></div>
			<form action="#" method="post">
				<textarea autofocus class="floatleft lic" name="lic_txt" placeholder="Enter licensee ..."><?php print $lic_txt;?></textarea>
				<textarea class="floatright mono" name="lic_info" placeholder="No license information created, yet. Paste an existing license into this box and click Check to show the licensee."><?php
					if (!empty($enc_lic_txt) && !empty($enc_lic_num) && !empty($enc_lic_type) && !empty($enc_lic_machines)) {
						print $enc_lic_txt . $lic_sep . $enc_lic_num . $lic_sep . $enc_lic_type . $lic_sep . $enc_lic_machines;
					} else {
						if (isset($lic_info)) {
							print $lic_info;
						}
					}
				?></textarea>
				<br class="clear"/>
				<div class="floatleft rightmargin">
					<table>
						<tr>
							<td>License type:</td>
						</tr>
						<tr>
							<td>Licensed devices:</td>
						</tr>
					</table>
				</div>
				<div class="floatleft rightmargin">
					<table>
						<tr>
							<td>
								<select name="lic_type" class="alignleft" title="Type of license.">
								<?php
								foreach ($lic_type_txt as $key => $value) {
									if ($key == $lic_type) {
										$selected = ' selected';
									} else {
										$selected = '';
									}
									
									print '<option value="' . $key . '"' . $selected . '>' . $value . '</option>';
								}?>
								</select>
							</td>
						</tr>
						<tr>
							<td>
								<select name="lic_machines" class="alignleft" title="Number of licensed machines.">
									<?php
									$x = 0;
									for ($i = 0; $i < 1000; $i += $x) {
										if ($i == $lic_machines) {
											$selected = ' selected';
										} else {
											$selected = '';
										}
										
										print '<option value="' . $i . '"' . $selected . '>' . $i .'</option>';
										
										if ($i <10) {
											$i++;
										} else {
											if ($i <100) {
												$x = 5;
											} else {
												$x = 50;
											}
										}
									}
									
									if ($lic_machines == -1) {
										$selected = ' selected';
									} else {
										$selected = '';
									}
									?><option value="-1"<?php print $selected;?>>unlimited</option>
								</select>
							</td>
						</tr>
					</table>
				</div>
				<div class="floatright">
					<input type="text" id="lic_num" name="lic_num" value="<?php print $lic_num;?>" placeholder="No license number"/>
					<span  title="Generate a random license number instead of a unique one from the license information."><input type="checkbox" id="lic_num_random" name="lic_num_random" <?php if ($lic_num_random) { print 'checked'; }?>/><label for="lic_num_random">Random?</label></span>
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
<?php


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
	 * Generates a random Initialization vector
	 * 
	 * @param integer $length
	 * @return string $iv
	 */
	private function getRandomIV($length) {
		$iv = '';
		while($length-- > 0) {
			$iv .= chr(mt_rand() & 0xff);
		}
		return $iv;
	}// END: getRandomIV()
	
	
	/**
	 * Sets password to use for encryption/decryption
	 * 
	 * @param string $password
	 */
	public function setKey($password) {
		$this->password = $password;
	}// END: setKey()

	
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
			$enc_text = $this->getRandomIV($iv_length);
			$iv = substr($this->password ^ $enc_text, 0, 512);
			
			while ($i < $n) {
				$block = substr($plain_text, $i, 16) ^ pack('H*', sha1($iv));
				$enc_text .= $block;
				$iv = substr($block . $iv, 0, 512) ^ $this->password;
				$i += 16;
			}
			
			return base64_encode($enc_text);
		}
	}// END: encrypt()

	/**
	 * Decodes and decrypts given payload and returns result.
	 * 
	 * @param string $enc_text - base64 encoded and XOR encrypted payload
	 * @param integer $iv_length - length of IV
	 * @return string $plain_text - decrypted and decoded
	 */
	public function decrypt($enc_text, $iv_length = 16) {
		$enc_text = base64_decode($enc_text);
		$n = strlen($enc_text);
		$i = $iv_length;
		$plain_text = '';
		$iv = substr($this->password ^ substr($enc_text, 0, $iv_length), 0, 512);
		
		while ($i < $n) {
			$block = substr($enc_text, $i, 16);
			$plain_text .= $block ^ pack('H*', sha1($iv));
			$iv = substr($block . $iv, 0, 512) ^ $this->password;
			$i += 16;
		}
		
		return stripslashes(preg_replace('/\\x13\\x00*$/', '', $plain_text));
	}// END: decrypt()
}// END: xorCrypt()


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
}// END: uuid()


/**
 * Checks if an encrypted payload contains valid license information.
 * 
 * @param string $lines
 * @return array $license
 */
function checkLicense($lines) {
	global $xorCrypt;

	$license = array('info'=>'', 'guid'=>'', 'type'=>'', 'num'=>'');

	if (isset($lines) && !empty($lines)) {
		if(count($lines) == 4) {
			$license['info'] = $lines[0];
			$license['guid'] = $lines[1];
			$license['type'] = $lines[2];
			$license['num']  = $lines[3];
		}
	}

	if ($license['info'] != '' && $license['guid'] != '' && $license['type'] != '' && $license['num'] != '') {
		$license['info'] = $xorCrypt->decrypt($license['info']);
		$license['guid'] = $xorCrypt->decrypt($license['guid']);
		$license['type'] = $xorCrypt->decrypt($license['type']);
		$license['num']  = $xorCrypt->decrypt($license['num']);
	} else {
		$license['info'] = '';
		$license['guid'] = '';
		$license['type'] = '0';
		$license['num']  = '0';
	}
	
	return $license;
}// END: checkLicense()
?>