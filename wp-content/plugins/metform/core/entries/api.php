<?php

namespace MetForm\Core\Entries;

use MetForm\Core\Integrations\Get_Response;
use MetForm\Core\Integrations\Mail_Chimp;

defined('ABSPATH') || exit;

class Api extends \MetForm\Base\Api
{

    public function config()
    {
        $this->prefix = 'entries';
        $this->param = "/(?P<id>\w+)";
    }

    public function post_insert()
    {
        $url = wp_get_referer();
        $post_id = url_to_postid($url);
        $post_id;

        $id = $this->request['id'];

        $form_data = $this->request->get_params();
        $file_data = $this->request->get_file_params();

        // Snapshot the last PHP error so we can tell whether the submission itself
        // triggered one (the real cause behind an otherwise generic failure).
        $error_before = error_get_last();

        // A FATAL error / uncaught exception inside submit() aborts the request before the
        // status-0 logger below can ever run — yet that is exactly the vague "Something went
        // wrong." case an admin most needs to see. Arm a shutdown guard that records the fatal,
        // but only if submit() never returned normally (it is disarmed via $completed right after
        // the call), so a normal rejection is never double-logged.
        $completed = false;
        register_shutdown_function(function () use (&$completed, $id, $post_id, $url, $form_data, $file_data, $error_before) {
            if ($completed) {
                return; // submit() returned — the status-0 path below already handled logging.
            }

            $fatal = error_get_last();

            // Only genuine fatals abort mid-submission; a non-fatal end (e.g. client
            // disconnect) is not a submission failure worth recording.
            if (empty($fatal) || !isset($fatal['type']) || !in_array($fatal['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
                return;
            }

            // Ignore a stale fatal that was already present before this submission ran.
            if (
                is_array($error_before)
                && isset($error_before['message'], $error_before['file'], $error_before['line'])
                && $error_before['message'] === $fatal['message']
                && $error_before['file'] === $fatal['file']
                && $error_before['line'] === $fatal['line']
            ) {
                return;
            }

            // A fatal leaves no response object, so this is always an unexpected failure with
            // no message shown to the visitor; the exact PHP cause becomes the log headline.
            $context = [
                'type'       => '',
                'unexpected' => true,
                'page_id'    => $post_id,
                'page_url'   => is_string($url) ? $url : '',
                'user'       => is_user_logged_in() ? ('Logged in (#' . get_current_user_id() . ')') : 'Guest',
                'fields'     => $this->collect_submitted_field_keys($form_data),
                'has_files'  => !empty($file_data),
                'debug'      => $this->format_php_error($fatal),
            ];

            do_action('metform_submission_failed', intval($id), [], $context);
        });

        $response = Action::instance()->submit($id, $form_data, $file_data, $post_id);
        $completed = true;

        // Notify the logger on every rejected submission (status 0), passing along the
        // failure_type that Action::submit() tagged on the response at its source. That tag
        // is what the Pro logger uses to decide what to keep: it stores only UNEXPECTED
        // failures a developer/admin needs to see — the generic "Something went wrong.",
        // nonce → "Unauthorized submission.", integration/config errors, or a PHP error —
        // and skips the handled ones (entry limit, scheduling, validation, captcha, login
        // required, file upload, duplicate, form access) that already showed the visitor a
        // clear, self-explanatory message.
        if (is_object($response) && empty($response->status)) {
            $messages = (isset($response->error) && is_array($response->error)) ? $response->error : [];

            // Drop the generic fallback so it never pollutes a typed headline.
            $specific = $this->filter_specific_messages($messages);

            // "Unexpected" = no source tag AND no specific message reached the visitor, i.e.
            // a vague/generic failure — so the log surfaces the exact technical cause instead.
            $unexpected = empty($response->failure_type) && empty($specific);

            // Diagnostic context to help debug (no field values / no IP).
            $context = [
                'type'       => (isset($response->failure_type) && is_string($response->failure_type)) ? sanitize_key($response->failure_type) : '',
                'unexpected' => $unexpected,
                'page_id'    => $post_id,
                'page_url'   => is_string($url) ? $url : '',
                'user'       => is_user_logged_in() ? ('Logged in (#' . get_current_user_id() . ')') : 'Guest',
                'fields'     => $this->collect_submitted_field_keys($form_data),
                'has_files'  => !empty($file_data),
                'debug'      => $this->capture_php_error($error_before),
            ];

            do_action('metform_submission_failed', intval($id), $specific, $context);
        }

        return $response;
    }

    /**
     * Return a description of a PHP error the submission triggered, if any, as
     * "message in path:line" (path relative to the WordPress root). Empty when no new
     * error occurred (the failure was a logic path rather than a PHP error).
     *
     * @param array|null $error_before The last PHP error captured before submit ran.
     * @return string
     */
    private function capture_php_error($error_before)
    {
        $error = error_get_last();

        if (empty($error) || !isset($error['message'])) {
            return '';
        }

        // Ignore a stale error that was already present before the submission ran.
        if (
            is_array($error_before)
            && isset($error_before['message'], $error_before['file'], $error_before['line'])
            && $error_before['message'] === $error['message']
            && $error_before['file'] === $error['file']
            && $error_before['line'] === $error['line']
        ) {
            return '';
        }

        return $this->format_php_error($error);
    }

    /**
     * Format a PHP error array (from error_get_last()) as "message in path:line", with the
     * path made relative to the WordPress root. Returns '' for an empty/malformed error.
     *
     * @param array|null $error
     * @return string
     */
    private function format_php_error($error)
    {
        if (empty($error) || !isset($error['message'])) {
            return '';
        }

        // An uncaught-exception fatal carries its whole multi-line stack trace in the message;
        // keep only the first line (the actual error) since file:line is appended separately.
        $raw     = explode("\n", (string) $error['message'], 2)[0];
        $message = sanitize_text_field($raw);
        $file    = isset($error['file']) ? str_replace(ABSPATH, '', $error['file']) : '';
        $line    = isset($error['line']) ? intval($error['line']) : 0;

        return $file !== '' ? ($message . ' in ' . $file . ':' . $line) : $message;
    }

    /**
     * Return only the specific, meaningful error messages — dropping empty entries and the
     * generic "Something went wrong." fallback that is present on every rejected response.
     *
     * @param array $messages
     * @return array
     */
    private function filter_specific_messages($messages)
    {
        $generic = trim(html_entity_decode(wp_strip_all_tags(esc_html__('Something went wrong.', 'metform')), ENT_QUOTES));

        $specific = [];
        foreach ((array) $messages as $message) {
            $decoded = trim(html_entity_decode((string) $message, ENT_QUOTES));

            if ($decoded === '' || $decoded === $generic) {
                continue;
            }

            $specific[] = $message;
        }

        return $specific;
    }

    /**
     * Collect submitted field names (keys only — never values) for failure diagnostics,
     * excluding internal/technical keys.
     *
     * @param mixed $form_data
     * @return array
     */
    private function collect_submitted_field_keys($form_data)
    {
        if (!is_array($form_data)) {
            return [];
        }

        $ignore = ['hidden-fields', 'form_id', 'g-recaptcha-response', 'g-recaptcha-response-v3', 'mf-captcha-challenge', 'mf_captcha_challenge', '_wpnonce', 'action'];

        $keys = [];
        foreach (array_keys($form_data) as $key) {
            if (in_array($key, $ignore, true)) {
                continue;
            }
            $keys[] = sanitize_text_field($key);

            // Keep the diagnostic list bounded.
            if (count($keys) >= 40) {
                break;
            }
        }

        return $keys;
    }
    public function get_export()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        $id = $this->request['id'];

        return Export::instance()->export_data($id);
    }

    public function get_get_response_list_id()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        $post_id = $this->request['id'];
        return get_option('wpmet_get_response_list_' . $post_id);
    }

    public function get_paypal()
    {

        $args = [
            'method' => (isset($this->request['action']) ? $this->request['action'] : ''),
            'action' => (isset($this->request['id']) ? $this->request['id'] : ''),
            'entry_id' => (isset($this->request['entry_id']) ? $this->request['entry_id'] : ''),
        ];

        if (class_exists('\MetForm_Pro\Core\Integrations\Payment\Paypal')) {
            return \MetForm_Pro\Core\Integrations\Payment\Paypal::instance()->init($args, $this->request);
        }
        return 'Pro needed';
    }

    public function get_stripe()
    {
        $args = [
            'method' => (isset($this->request['action']) ? $this->request['action'] : ''),
            'action' => (isset($this->request['id']) ? $this->request['id'] : ''),
            'entry_id' => (isset($this->request['entry_id']) ? $this->request['entry_id'] : ''),
            'token' => (isset($this->request['token']) ? $this->request['token'] : ''),
        ];
        if (class_exists('\MetForm_Pro\Core\Integrations\Payment\Stripe')) {
            return \MetForm_Pro\Core\Integrations\Payment\Stripe::instance()->init($args);
        }
        return 'Pro needed';
    }

    public function get_views()
    {
        return $this->request->get_params();
    }

    public function get_get_response_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        $post_id = $this->request['id'];
        return get_option('wpmet_get_response_list_' . $post_id);
    }

    public function get_store_get_response_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        if (class_exists('\MetForm_Pro\Core\Integrations\Email\Getresponse\Get_Response')) {

            $post_id = $this->request['id'];
            $data = \MetForm\Core\Forms\Action::instance()->get_all_data($post_id);
            $api_key = isset($data['mf_get_reponse_api_key']) ? $data['mf_get_reponse_api_key'] : null;

            $get_response_list = \MetForm_Pro\Core\Integrations\Email\Getresponse\Get_Response::get_list($api_key);

            delete_option('wpmet_get_response_list_' . $post_id, $get_response_list);
            update_option('wpmet_get_response_list_' . $post_id, $get_response_list);

            return get_option('wpmet_get_response_list_' . $post_id);
        }

        return 'error';
    }

    public function get_get_mailchimp_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}
        $post_id = $this->request['id'];
        return get_option('wpmet_get_mailchimp_list_' . $post_id);
    }

    public function get_store_mailchimp_list()
    {
        $nonce = $this->request->get_header('X-WP-Nonce');

        if(!current_user_can('manage_options')) {
			return;
		}


        if(!wp_verify_nonce($nonce, 'wp_rest')) {
            return [
				'status'    => 'fail',
				'message'   => [  __( 'Nonce mismatch.', 'metform' ) ],
			];
        }

        $post_id = $this->request['id'];
        $data = \MetForm\Core\Forms\Action::instance()->get_all_data($post_id);
        $api_key = $data['mf_mailchimp_api_key'];
        
        if (!preg_match('/^[a-z0-9]{32}-[a-z0-9]{3,4}$/', $api_key)) {
            return [
				'status'    => 'fail',
				'message'   => [  __( 'Invalid_api_key.', 'metform' ) ],
			];
        }

        $mailChimp_list = json_decode(Mail_Chimp::get_list($api_key)['body']);

        delete_option('wpmet_get_mailchimp_list_' . $post_id, $mailChimp_list);
        update_option('wpmet_get_mailchimp_list_' . $post_id, $mailChimp_list);

        return get_option('wpmet_get_mailchimp_list_' . $post_id, $mailChimp_list);
    }

    /**
     * Get MailerLite groups list (fetch from API and cache)
     */
    public function get_store_mailerlite_groups()
    {
        $nonce = $this->request->get_header('X-WP-Nonce');

        if(!current_user_can('manage_options')) {
            return;
        }

        if(!wp_verify_nonce($nonce, 'wp_rest')) {
            return [
                'status'    => 'fail',
                'message'   => [__('Nonce mismatch.', 'metform')],
            ];
        }

        if (!class_exists('\MetForm_Pro\Core\Integrations\Mailerlite')) {
            return [
                'status'  => 'fail',
                'message' => [__('MailerLite integration is not available.', 'metform')],
            ];
        }

        // Get the API key from global settings
        $global_settings = \MetForm\Core\Admin\Base::instance()->get_settings_option();
        $api_key = isset($global_settings['mf_mailerlite_api_key']) ? $global_settings['mf_mailerlite_api_key'] : '';

        if (empty($api_key)) {
            return [
                'status'  => 'fail',
                'message' => [__('MailerLite API key is not configured. Please configure it in MetForm Settings.', 'metform')],
            ];
        }

        $mailerlite = new \MetForm_Pro\Core\Integrations\Mailerlite($api_key);
        $groups = $mailerlite->get_groups();

        if ($groups === false) {
            return [
                'status'  => 'fail',
                'message' => [$mailerlite->get_last_error()],
            ];
        }

        $formatted_groups = array();
        foreach ($groups as $group) {
            $formatted_groups[] = array(
                'id'   => $group['id'],
                'name' => $group['name'],
            );
        }

        return [
            'status' => 'success',
            'groups' => $formatted_groups,
        ];
    }

    /**
     * Get MailerLite fields list for mapping
     */
    public function get_mailerlite_fields()
    {
        $nonce = $this->request->get_header('X-WP-Nonce');

        if(!current_user_can('manage_options')) {
            return;
        }

        if(!wp_verify_nonce($nonce, 'wp_rest')) {
            return [
                'status'    => 'fail',
                'message'   => [__('Nonce mismatch.', 'metform')],
            ];
        }

        if (!class_exists('\MetForm_Pro\Core\Integrations\Mailerlite')) {
            return [
                'status'  => 'fail',
                'message' => [__('MailerLite integration is not available.', 'metform')],
            ];
        }

        // Get the API key from global settings
        $global_settings = \MetForm\Core\Admin\Base::instance()->get_settings_option();
        $api_key = isset($global_settings['mf_mailerlite_api_key']) ? $global_settings['mf_mailerlite_api_key'] : '';

        if (empty($api_key)) {
            return [
                'status'  => 'fail',
                'message' => [__('MailerLite API key is not configured.', 'metform')],
            ];
        }

        $post_id = $this->request['id'];
        $group_id = isset($this->request['group_id']) ? sanitize_text_field($this->request['group_id']) : '';

        $mailerlite = new \MetForm_Pro\Core\Integrations\Mailerlite($api_key);

        // Fetch MailerLite subscriber fields (these are account-wide in MailerLite)
        $fields = $mailerlite->get_fields_for_mapping();

        if (empty($fields) && $mailerlite->get_last_error()) {
            return [
                'status'  => 'fail',
                'message' => [$mailerlite->get_last_error()],
            ];
        }

        // Get form fields for mapping
        $form_fields = array();
        $map_data = \MetForm\Core\Entries\Action::instance()->get_fields($post_id);
        if (!empty($map_data)) {
            foreach ($map_data as $key => $field) {
                // $field can be stdClass (from Elementor JSON) or array
                if (is_object($field)) {
                    $name  = isset($field->mf_input_name) ? $field->mf_input_name : $key;
                    $label = isset($field->mf_input_label) ? $field->mf_input_label : $key;
                } else {
                    $name  = isset($field['mf_input_name']) ? $field['mf_input_name'] : $key;
                    $label = isset($field['mf_input_label']) ? $field['mf_input_label'] : $key;
                }
                $form_fields[] = array(
                    'name'  => $name,
                    'label' => $label,
                );
            }
        }

        // Get saved field mapping if exists
        $saved_mapping = get_option('mf_mailerlite_field_mapping_' . $post_id, array());

        return [
            'status'            => 'success',
            'group_id'          => $group_id,
            'mailerlite_fields' => $fields,
            'form_fields'       => $form_fields,
            'saved_mapping'     => $saved_mapping,
        ];
    }

    public function get_google_spreadsheet_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        if (!class_exists('\MetForm_Pro\Core\Integrations\Google_Sheet\WF_Google_Sheet')) {
            
            return 'Pro needed';
        }

        $google      = new \MetForm_Pro\Core\Integrations\Google_Sheet\WF_Google_Sheet;
        $response = $google->get_all_spreadsheets();
        return $response ;
    }

    public function get_google_sheet_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        if (!class_exists('\MetForm_Pro\Core\Integrations\Google_Sheet\WF_Google_Sheet')) {
            
            return 'Pro needed';
        }

        // $spreadsheetID = $this->request['spreadsheetID'];
        $sheetID = $this->request['sheetID'];


        $google      = new \MetForm_Pro\Core\Integrations\Google_Sheet\WF_Google_Sheet;
        $response = $google->get_sheets_details_from_spreadsheet($sheetID);
        return $response ;
    }
	public function get_dropbox_folder_list()
    {
        if(!current_user_can('manage_options')) {
			return;
		}

        if (!class_exists('\MetForm_Pro\Core\Integrations\Dropbox\MF_Dropbox')) {

            return 'Pro needed';
        }

        $dropbox = new \MetForm_Pro\Core\Integrations\Dropbox\MF_Dropbox;
        $response = $dropbox->get_all_dropbox_folders();
        return $response;
    }
    
    public function get_google_drive_folder_list()
    {
        $nonce = $this->request->get_header('X-WP-Nonce');

        if(!current_user_can('manage_options')) { 
            return;
        } 

        if(!wp_verify_nonce($nonce, 'wp_rest')) {
            return [
				'status'    => 'fail',
				'message'   => [  __( 'Nonce mismatch.', 'metform' ) ],
			];
        }
        
        if (!class_exists('\MetForm_Pro\Core\Integrations\Google_Drive\MF_Google_Drive')) {                       
            return 'Pro needed';
        }
        $google      = new \MetForm_Pro\Core\Integrations\Google_Drive\MF_Google_Drive;
        $response = $google->get_all_google_drive_folders();  
        
              
        return json_encode(['folders' => $response]);
    }

}
