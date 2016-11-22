<?php

namespace nl\naturalis\medialib\util;

use nl\naturalis\medialib\util\context\Context;

/** 
 * @author ayco_holleman
 * 
 */
class EmailUtil
{

	
	/**
	 * @param Context $context
	 */
	public static function sendDefaultEmail (Context $context, $subject)
	{
		$mailTo = explode(',', $context->getConfig()->mail->to);
		if (count($mailTo) === 0) {
			return false;
		}
		$mail = new \PHPMailer();
		foreach ($mailTo as $address) {
			$mail->AddAddress($address);
		}
		$mail->SetFrom('medialib@naturalis.nl', 'NBC Media Library');
		$mail->Subject = $subject;
		if (is_file($context->getLogFile())) {
			$mail->Body = file_get_contents($context->getLogFile());
			$mail->AddAttachment($context->getLogFile());
		}
		else {
			$mail->Body = 'Log file not available: ' . $context->getLogFile();
		}
		return $mail->Send();
	}

}