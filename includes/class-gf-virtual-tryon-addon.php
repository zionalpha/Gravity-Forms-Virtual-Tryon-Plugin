<?php
if (!class_exists('GFForms')) {
    die();
}

class GF_Virtual_Tryon_Addon extends GFAddOn {
    protected $_version = '1.0.0';
    protected $_min_gravityforms_version = '2.5';
    protected $_slug = 'virtual-tryon-gravityforms';
    protected $_path = 'virtual-tryon-gravityforms/virtual-tryon-gravityforms.php';
    protected $_full_path = __FILE__;
    protected $_title = 'Virtual Try-on for Gravity Forms';
    protected $_short_title = 'Virtual Try-on';
    private $api_token;

    private static $_instance = null;

    public static function get_instance() {
        if (self::$_instance == null) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    public function init() {
        parent::init();
        $this->api_token = $this->get_plugin_setting('api_token');
        add_action('gform_after_submission', array($this, 'process_virtual_tryon'), 10, 2);
        add_action('wp_ajax_gf_virtual_tryon_test', array($this, 'ajax_test_connection'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_styles'));
        add_action('gform_after_submission', array($this, 'display_tryon_result'), 11, 2);
        add_action('gform_loaded', array($this, 'maybe_add_result_field'));
    }

    public function plugin_settings_fields() {
        return array(
            array(
                'title' => esc_html__('Virtual Try-on Settings', 'virtual-tryon-gravityforms'),
                'fields' => array(
                    array(
                        'name' => 'api_token',
                        'label' => esc_html__('Replicate API Token', 'virtual-tryon-gravityforms'),
                        'type' => 'text',
                        'class' => 'medium',
                        'required' => true,
                        'tooltip' => esc_html__('Enter your Replicate API token', 'virtual-tryon-gravityforms')
                    )
                )
            )
        );
    }

    public function form_settings_fields($form) {
        return array(
            array(
                'title' => esc_html__('Virtual Try-on Field Mapping', 'virtual-tryon-gravityforms'),
                'fields' => array(
                    array(
                        'name' => 'face_image_field',
                        'label' => esc_html__('Face Image Field', 'virtual-tryon-gravityforms'),
                        'type' => 'select',
                        'choices' => $this->get_file_fields($form)
                    ),
                    array(
                        'name' => 'model_image_field',
                        'label' => esc_html__('Model Image Field', 'virtual-tryon-gravityforms'),
                        'type' => 'select',
                        'choices' => $this->get_file_fields($form)
                    ),
                    array(
                        'name' => 'result_field',
                        'label' => esc_html__('Result Field', 'virtual-tryon-gravityforms'),
                        'type' => 'select',
                        'choices' => $this->get_file_fields($form)
                    )
                )
            )
        );
    }

    public function process_virtual_tryon($entry, $form) {
        $settings = $this->get_form_settings($form);
        
        if (!$this->is_virtual_tryon_enabled($settings)) {
            return;
        }

        $face_image = rgar($entry, $settings['face_image_field']);
        $model_image = rgar($entry, $settings['model_image_field']);

        if (empty($face_image) || empty($model_image)) {
            return;
        }

        try {
            $result = $this->process_images($face_image, $model_image);
            if ($result && !empty($settings['result_field'])) {
                GFAPI::update_entry_field($entry['id'], $settings['result_field'], $result);
            }
        } catch (Exception $e) {
            $this->log_error(__METHOD__ . '(): ' . $e->getMessage());
        }
    }

    private function process_images($face_image, $model_image) {
        $prediction = $this->create_prediction($face_image, $model_image);
        if (!$prediction || !isset($prediction->id)) {
            throw new Exception('Failed to create prediction');
        }

        return $this->wait_for_prediction_result($prediction->id);
    }

    private function create_prediction($face_image, $model_image) {
        $response = wp_remote_post('https://api.replicate.com/v1/predictions', array(
            'headers' => array(
                'Authorization' => "Token {$this->api_token}",
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode(array(
                'version' => '39860afc',
                'input' => array(
                    'face_image' => $face_image,
                    'model_image' => $model_image
                )
            ))
        ));

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    private function wait_for_prediction_result($prediction_id, $max_attempts = 30) {
        $attempt = 0;
        do {
            $result = $this->get_prediction_status($prediction_id);
            if ($result->status === 'succeeded') {
                return $result->output;
            } elseif ($result->status === 'failed') {
                throw new Exception('Prediction failed');
            }
            sleep(2);
            $attempt++;
        } while ($attempt < $max_attempts);

        throw new Exception('Prediction timeout');
    }

    private function get_prediction_status($prediction_id) {
        $response = wp_remote_get(
            "https://api.replicate.com/v1/predictions/{$prediction_id}",
            array(
                'headers' => array(
                    'Authorization' => "Token {$this->api_token}"
                )
            )
        );

        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }

        return json_decode(wp_remote_retrieve_body($response));
    }

    private function get_file_fields($form) {
        $fields = array(array('label' => 'Select a field', 'value' => ''));
        
        foreach ($form['fields'] as $field) {
            if ($field->type === 'fileupload') {
                $fields[] = array(
                    'label' => $field->label,
                    'value' => $field->id
                );
            }
        }
        
        return $fields;
    }

    private function is_virtual_tryon_enabled($settings) {
        return !empty($settings['face_image_field']) && 
               !empty($settings['model_image_field']) && 
               !empty($settings['result_field']);
    }

    public function ajax_test_connection() {
        check_ajax_referer('gf_virtual_tryon_test', 'nonce');
        
        if (!$this->api_token) {
            wp_send_json_error('API token not configured');
        }

        try {
            $response = wp_remote_get('https://api.replicate.com/v1/predictions', array(
                'headers' => array('Authorization' => "Token {$this->api_token}")
            ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            $code = wp_remote_retrieve_response_code($response);
            if ($code !== 200) {
                throw new Exception('Invalid API token');
            }

            wp_send_json_success('Connection successful');
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function enqueue_frontend_styles() {
        wp_enqueue_style(
            'gf-virtual-tryon-frontend',
            GF_VIRTUAL_TRYON_URL . 'assets/css/frontend.css',
            array(),
            $this->_version
        );
    }
    
    public function display_tryon_result($entry, $form) {
        $settings = $this->get_form_settings($form);
        $result_field_id = $settings['result_field'];
        $result_url = rgar($entry, $result_field_id);
        
        if (!empty($result_url)) {
            echo '<div class="gf-virtual-tryon-result">';
            echo '<h3>' . esc_html__('Your Virtual Try-on Result', 'virtual-tryon-gravityforms') . '</h3>';
            echo '<img src="' . esc_url($result_url) . '" alt="Try-on Result">';
            echo '</div>';
        }
    }

    public function form_settings_fields($form) {
        return array(
            array(
                'title' => esc_html__('Virtual Try-on Field Mapping', 'virtual-tryon-gravityforms'),
                'fields' => array(
                    array(
                        'name' => 'face_image_field',
                        'label' => esc_html__('Face Image Field', 'virtual-tryon-gravityforms'),
                        'type' => 'select',
                        'choices' => $this->get_file_fields($form)
                    ),
                    array(
                        'name' => 'model_image_field',
                        'label' => esc_html__('Model Image Field', 'virtual-tryon-gravityforms'),
                        'type' => 'select',
                        'choices' => $this->get_file_fields($form)
                    )
                )
            )
        );
    }
    
    // Add this new method to create the result field
    public function add_result_field($form) {
        // Check if result field already exists
        foreach ($form['fields'] as $field) {
            if ($field->type === 'hidden' && $field->label === 'Virtual Try-on Result') {
                return $form;
            }
        }
    
        // Create new hidden field
        $result_field = GF_Fields::create(array(
            'type' => 'hidden',
            'label' => 'Virtual Try-on Result',
            'id' => GFFormsModel::get_next_field_id($form['fields'])
        ));
    
        $form['fields'][] = $result_field;
        GFAPI::update_form($form);
    
        return $form;
    }
    

    
    // Add this method to handle result field creation
    public function maybe_add_result_field() {
        $forms = GFAPI::get_forms();
        foreach ($forms as $form) {
            $settings = $this->get_form_settings($form);
            if ($this->is_virtual_tryon_enabled($settings)) {
                $this->add_result_field($form);
            }
        }
    }
}