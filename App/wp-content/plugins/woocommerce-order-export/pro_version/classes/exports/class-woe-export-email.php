<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class WOE_Export_Email extends WOE_Export {

	static $from = '';
	static $from_name = '';

	static $files		     = array();
	protected $attachments_files = array();

	public function run_export( $filename, $filepath, $num_retries, $is_last_order = true ) {

		$dont_rename_files = apply_filters( "woe_export_email_dont_rename_files", false );

		if ( empty( $this->attachments_files ) ) {
			if( $dont_rename_files ) {
				$newfilepath = $filepath; // use original file path with WRONG filename!
			} else {
				//legacy code for Wordpress 6.1 or less
				//must rename tmp file
				$newfilepath = dirname( $filepath ) . "/" . $filename;
				//die($newfilepath);
				if ( ! @copy( $filepath, $newfilepath ) ) {
					return __( "Can't rename temporary file", 'woocommerce-order-export' );
				}
			}
		    $this->attachments_files[] = array(
			'filename' => $filename,
			'filepath' => $newfilepath,
		    );
		    static::$files = array_merge(static::$files, $this->attachments_files);
		}

		if ( !empty( $this->destination['email_send_separate_files_in_one_email'] ) ) {

		    if ( ! $is_last_order ) {
			$this->finished_successfully = true;
			return;
		    }

		    $this->attachments_files = static::$files;
		}

		$attachments_files = $this->attachments_files;

		$to      = apply_filters( "woe_export_email_recipients",
			preg_split( "#,|\r?\n#", $this->destination['email_recipients'], -1, PREG_SPLIT_NO_EMPTY ) );

		//wpml compatibility
		if( ! empty( $to ) ) {
			do_action( 'wpml_switch_language_for_email', $to[0] );
		}

		$subject = apply_filters( "woe_export_email_subject",
			WC_Order_Export_Pro_Engine::make_filename( $this->destination['email_subject'], false ) );

		$message = WC_Order_Export_Pro_Engine::make_filename( $this->destination['email_body'], false );
		if ( ! empty( $this->destination['email_use_wc_template'] ) ) {
			foreach(WC_Emails::instance()->get_emails() as $email) {
				if($email->id == $this->destination['email_wc_template']) {
					$wc_email = $email;
					break;
				}
			}
			if( isset( $wc_email ) ) {
				$wc_email->object = isset( $this->destination['changed_order_id'] ) ? wc_get_order( $this->destination['changed_order_id'] ) :
									wc_get_orders( array() )[0];

				do_action( 'woe_export_email_before_get_wc_template_content', $wc_email );

				$message .= $wc_email->style_inline( $wc_email->get_content() );

				// some emails can have extra attachments -  invoices , for example
				$attachments = $wc_email->get_attachments();
				foreach($attachments as $filepath){
					$attachments_files[] = array(
					'filename' => basename($filepath),
					'filepath' => $filepath,
					);
				}
			}
		}
		// should use json/xml as body
		if ( ! empty( $this->destination['email_body_append_file_contents'] ) ) {
		    foreach ($attachments_files as $file) {
			$message .= file_get_contents( $file['filepath'] );
		    }
		}
		if ( empty( $message ) ) {
			$message = __( "Please, review the attachment", 'woocommerce-order-export' );
		}
		$message = str_replace( "{orders_exported}", WC_Order_Export_Pro_Engine::$orders_exported, $message );
		$message = apply_filters( "woe_export_email_message", $message );

		$headers = array();
		if ( trim($message) != wp_strip_all_tags( $message ) ) {
			$headers[] = "Content-Type: text/html";
		} else {
			$headers[] = "Content-Type: text/plain";
		}

		//From config
		self::$from_name = $this->destination['email_from_name'];
		add_action( 'wp_mail_from_name', function ( $original_email_from_name ) {
			return WOE_Export_Email::$from_name;
		} );

		self::$from = $this->destination['email_from'];
		if ( self::$from ) {
			add_action( 'wp_mail_from', function ( $original_email_from ) {
				return WOE_Export_Email::$from;
			} );
		}

		// have to add CC?
		if ( ! empty( $this->destination['email_recipients_cc'] ) ) {
			$cc_emails = preg_split( "#,|\r?\n#", $this->destination['email_recipients_cc'], -1,
				PREG_SPLIT_NO_EMPTY );
			foreach ( $cc_emails as $cc_email ) {
				$headers[] = "Cc: " . $cc_email;
			}
		}

		// have to add BCC?
		if ( ! empty( $this->destination['email_recipients_bcc'] ) ) {
			$bcc_emails = preg_split( "#,|\r?\n#", $this->destination['email_recipients_bcc'], -1,
				PREG_SPLIT_NO_EMPTY );
			foreach ( $bcc_emails as $bcc_email ) {
				$headers[] = "Bcc: " . $bcc_email;
			}
		}

		if( $dont_rename_files ){ // pass associative array
			$attachments = array();
			foreach($attachments_files as $f)
				$attachments[$f['filename']] = $f['filepath'];
			$attachments = apply_filters( "woe_export_email_attachments", $attachments);
		} else //legacy code
			$attachments = apply_filters( "woe_export_email_attachments", array_map(function ($v) { return $v['filepath']; }, $attachments_files) );

//                 if ( did_action( 'sendinblue_init' ) ) {
//                     $headers = [];
//                 }

		try {
			$result = wp_mail( $to, $subject, $message, $headers, $attachments );
		} catch ( Exception $e ) {
			//$msg = $e->getMessage();
			$result = false;
		}

		if( ! empty( $to ) ) {
			do_action( 'wpml_restore_language_from_email' );
		}

		if ( ! $result ) {
			global $ts_mail_errors;
			global $phpmailer;
			if ( ! isset( $ts_mail_errors ) ) {
				$ts_mail_errors = array();
			}
			if ( isset( $phpmailer ) ) {
				$ts_mail_errors[] = $phpmailer->ErrorInfo;
			}
		}
		if ( empty( $ts_mail_errors ) ) {
			$this->finished_successfully = true;
            /* translators: Sending a file by email */
            $return                      = sprintf( __( "File '%1\$s' has sent to '%2\$s'", 'woocommerce-order-export' ),
				 join( ", ", array_map(function ($v) { return $v['filename']; }, $attachments_files) ), join( ",", $to ) );
			if( isset( $cc_emails ) ) {
                /* translators: Header of the received message cc emails */
				$return = sprintf( __( "%1\$s, CC: '%2\$s'", 'woocommerce-order-export' ),
				$return, join( ", ", $cc_emails ) );
			}
			if( isset( $bcc_emails ) ) {
                /* translators: Header of the received message bcc emails */
				$return = sprintf( __( "%1\$s, BCC: '%2\$s'", 'woocommerce-order-export' ),
				$return, join( ", ", $bcc_emails ) );
			}
		} else {
			$return = implode( ';', $ts_mail_errors );
		}

		$is_last_num_retries = $this->get_num_of_retries() === $num_retries;

		if ( empty( $this->destination['email_send_separate_files_in_one_email'] )
		    || $is_last_order && ( $this->finished_successfully || $is_last_num_retries )
		) {
		    foreach ($attachments as $file) {
		       //delete renamed copy
		       if(!$dont_rename_files)
                   wp_delete_file( $file );
		    }
		    static::$files	     = array();
		    $this->attachments_files = array();
		}

		return $return;
	}
}
