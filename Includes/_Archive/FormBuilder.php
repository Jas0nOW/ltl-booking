<?php
if ( ! defined('ABSPATH') ) exit;

/**
 * Form Builder Engine
 * 
 * Allows creating custom booking forms with:
 * - Drag & drop field builder
 * - Field types: text, textarea, select, checkbox, radio, date, file
 * - Required fields
 * - Conditional logic (if X then show Y)
 * - Per-mode and per-service customization
 * 
 * @package LazyBookings
 */
class LTLB_Form_Builder {

    /**
     * Get form fields for service/mode
     * 
     * @param int $service_id Service ID (0 for global)
     * @param string $mode Mode: hotel, service
     * @return array Form fields
     */
    public function get_form_fields( int $service_id = 0, string $mode = '' ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_form_fields';

        $where = "1=1";
        $params = [];

        if ( $service_id > 0 ) {
            $where .= " AND (service_id = %d OR service_id IS NULL)";
            $params[] = $service_id;
        } else {
            $where .= " AND service_id IS NULL";
        }

        if ( ! empty( $mode ) ) {
            $where .= " AND (mode = %s OR mode IS NULL)";
            $params[] = $mode;
        }

        $query = "SELECT * FROM $table WHERE $where ORDER BY sort_order ASC, id ASC";

        $fields = empty( $params )
            ? $wpdb->get_results( $query )
            : $wpdb->get_results( $wpdb->prepare( $query, $params ) );

        // Parse JSON fields
        foreach ( $fields as $field ) {
            $field->options = ! empty( $field->options ) ? json_decode( $field->options, true ) : [];
            $field->validation = ! empty( $field->validation ) ? json_decode( $field->validation, true ) : [];
            $field->conditions = ! empty( $field->conditions ) ? json_decode( $field->conditions, true ) : [];
        }

        return $fields;
    }

    /**
     * Create custom field
     * 
     * @param array $data Field data
     * @return int|WP_Error Field ID or error
     */
    public function create_field( array $data ) {
        global $wpdb;

        // Validate required fields
        if ( empty( $data['label'] ) || empty( $data['type'] ) ) {
            return new WP_Error( 'missing_data', __( 'Label and type are required', 'ltl-bookings' ) );
        }

        $table = $wpdb->prefix . 'ltlb_form_fields';

        $field_data = [
            'label' => sanitize_text_field( $data['label'] ),
            'field_key' => sanitize_key( $data['field_key'] ?? $this->generate_field_key( $data['label'] ) ),
            'type' => sanitize_text_field( $data['type'] ),
            'placeholder' => sanitize_text_field( $data['placeholder'] ?? '' ),
            'help_text' => sanitize_text_field( $data['help_text'] ?? '' ),
            'default_value' => sanitize_text_field( $data['default_value'] ?? '' ),
            'options' => ! empty( $data['options'] ) ? wp_json_encode( $data['options'] ) : null,
            'validation' => ! empty( $data['validation'] ) ? wp_json_encode( $data['validation'] ) : null,
            'conditions' => ! empty( $data['conditions'] ) ? wp_json_encode( $data['conditions'] ) : null,
            'is_required' => ! empty( $data['is_required'] ) ? 1 : 0,
            'service_id' => ! empty( $data['service_id'] ) ? intval( $data['service_id'] ) : null,
            'mode' => ! empty( $data['mode'] ) ? sanitize_text_field( $data['mode'] ) : null,
            'sort_order' => intval( $data['sort_order'] ?? 999 ),
            'is_active' => ! empty( $data['is_active'] ) ? 1 : 0,
            'created_at' => current_time( 'mysql' )
        ];

        $result = $wpdb->insert( $table, $field_data, [
            '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%s'
        ] );

        if ( ! $result ) {
            return new WP_Error( 'db_error', $wpdb->last_error );
        }

        return $wpdb->insert_id;
    }

    /**
     * Update field
     * 
     * @param int $field_id
     * @param array $data Update data
     * @return bool|WP_Error
     */
    public function update_field( int $field_id, array $data ) {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_form_fields';

        $update_data = [];
        $formats = [];

        $allowed_fields = [
            'label' => '%s',
            'placeholder' => '%s',
            'help_text' => '%s',
            'default_value' => '%s',
            'is_required' => '%d',
            'sort_order' => '%d',
            'is_active' => '%d'
        ];

        foreach ( $allowed_fields as $field => $format ) {
            if ( isset( $data[$field] ) ) {
                $update_data[$field] = $data[$field];
                $formats[] = $format;
            }
        }

        // Handle JSON fields
        if ( isset( $data['options'] ) ) {
            $update_data['options'] = wp_json_encode( $data['options'] );
            $formats[] = '%s';
        }

        if ( isset( $data['validation'] ) ) {
            $update_data['validation'] = wp_json_encode( $data['validation'] );
            $formats[] = '%s';
        }

        if ( isset( $data['conditions'] ) ) {
            $update_data['conditions'] = wp_json_encode( $data['conditions'] );
            $formats[] = '%s';
        }

        if ( empty( $update_data ) ) {
            return new WP_Error( 'no_data', __( 'No data to update', 'ltl-bookings' ) );
        }

        $update_data['updated_at'] = current_time( 'mysql' );
        $formats[] = '%s';

        $result = $wpdb->update(
            $table,
            $update_data,
            [ 'id' => $field_id ],
            $formats,
            [ '%d' ]
        );

        return $result !== false;
    }

    /**
     * Delete field
     * 
     * @param int $field_id
     * @return bool Success
     */
    public function delete_field( int $field_id ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_form_fields';

        return $wpdb->delete( $table, [ 'id' => $field_id ], [ '%d' ] ) !== false;
    }

    /**
     * Render field HTML
     * 
     * @param object $field Field data
     * @param mixed $value Current value
     * @return string HTML
     */
    public function render_field( object $field, $value = null ): string {
        $required = $field->is_required ? 'required' : '';
        $value = $value ?? $field->default_value;

        ob_start();
        ?>
        <div class="ltlb-form-field ltlb-field-<?php echo esc_attr( $field->type ); ?>" 
             data-field-key="<?php echo esc_attr( $field->field_key ); ?>"
             data-conditions="<?php echo esc_attr( wp_json_encode( $field->conditions ) ); ?>">
            
            <label for="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>">
                <?php echo esc_html( $field->label ); ?>
                <?php if ( $field->is_required ) : ?>
                    <span class="required">*</span>
                <?php endif; ?>
            </label>

            <?php
            switch ( $field->type ) {
                case 'text':
                case 'email':
                case 'tel':
                case 'url':
                case 'number':
                    ?>
                    <input type="<?php echo esc_attr( $field->type ); ?>" 
                           id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                           name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                           value="<?php echo esc_attr( $value ); ?>"
                           placeholder="<?php echo esc_attr( $field->placeholder ); ?>"
                           <?php echo $required; ?>>
                    <?php
                    break;

                case 'textarea':
                    ?>
                    <textarea id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                              name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                              placeholder="<?php echo esc_attr( $field->placeholder ); ?>"
                              rows="4"
                              <?php echo $required; ?>><?php echo esc_textarea( $value ); ?></textarea>
                    <?php
                    break;

                case 'select':
                    ?>
                    <select id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                            name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                            <?php echo $required; ?>>
                        <option value=""><?php esc_html_e( 'Select...', 'ltl-bookings' ); ?></option>
                        <?php foreach ( $field->options as $option ) : ?>
                            <option value="<?php echo esc_attr( $option['value'] ); ?>"
                                    <?php selected( $value, $option['value'] ); ?>>
                                <?php echo esc_html( $option['label'] ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'checkbox':
                    ?>
                    <label class="ltlb-checkbox-label">
                        <input type="checkbox" 
                               id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                               name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                               value="1"
                               <?php checked( $value, '1' ); ?>
                               <?php echo $required; ?>>
                        <?php echo esc_html( $field->help_text ); ?>
                    </label>
                    <?php
                    break;

                case 'radio':
                    ?>
                    <div class="ltlb-radio-group">
                        <?php foreach ( $field->options as $index => $option ) : ?>
                            <label class="ltlb-radio-label">
                                <input type="radio" 
                                       name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                                       value="<?php echo esc_attr( $option['value'] ); ?>"
                                       <?php checked( $value, $option['value'] ); ?>
                                       <?php echo $required; ?>>
                                <?php echo esc_html( $option['label'] ); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    break;

                case 'date':
                    ?>
                    <input type="date" 
                           id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                           name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                           value="<?php echo esc_attr( $value ); ?>"
                           <?php echo $required; ?>>
                    <?php
                    break;

                case 'file':
                    ?>
                    <input type="file" 
                           id="ltlb-field-<?php echo esc_attr( $field->field_key ); ?>"
                           name="custom_fields[<?php echo esc_attr( $field->field_key ); ?>]"
                           <?php echo $required; ?>>
                    <?php
                    break;
            }
            ?>

            <?php if ( ! empty( $field->help_text ) && $field->type !== 'checkbox' ) : ?>
                <small class="ltlb-help-text"><?php echo esc_html( $field->help_text ); ?></small>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Validate submitted field data
     * 
     * @param object $field Field config
     * @param mixed $value Submitted value
     * @return true|WP_Error
     */
    public function validate_field( object $field, $value ) {
        // Required check
        if ( $field->is_required && empty( $value ) ) {
            return new WP_Error( 'required', sprintf(
                __( '%s is required', 'ltl-bookings' ),
                $field->label
            ) );
        }

        // Type-specific validation
        switch ( $field->type ) {
            case 'email':
                if ( ! empty( $value ) && ! is_email( $value ) ) {
                    return new WP_Error( 'invalid_email', sprintf(
                        __( '%s must be a valid email', 'ltl-bookings' ),
                        $field->label
                    ) );
                }
                break;

            case 'url':
                if ( ! empty( $value ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
                    return new WP_Error( 'invalid_url', sprintf(
                        __( '%s must be a valid URL', 'ltl-bookings' ),
                        $field->label
                    ) );
                }
                break;

            case 'number':
                if ( ! empty( $value ) && ! is_numeric( $value ) ) {
                    return new WP_Error( 'invalid_number', sprintf(
                        __( '%s must be a number', 'ltl-bookings' ),
                        $field->label
                    ) );
                }
                break;
        }

        // Custom validation rules
        if ( ! empty( $field->validation ) ) {
            foreach ( $field->validation as $rule => $rule_value ) {
                switch ( $rule ) {
                    case 'min_length':
                        if ( strlen( $value ) < intval( $rule_value ) ) {
                            return new WP_Error( 'min_length', sprintf(
                                __( '%s must be at least %d characters', 'ltl-bookings' ),
                                $field->label,
                                $rule_value
                            ) );
                        }
                        break;

                    case 'max_length':
                        if ( strlen( $value ) > intval( $rule_value ) ) {
                            return new WP_Error( 'max_length', sprintf(
                                __( '%s must be no more than %d characters', 'ltl-bookings' ),
                                $field->label,
                                $rule_value
                            ) );
                        }
                        break;

                    case 'pattern':
                        if ( ! preg_match( $rule_value, $value ) ) {
                            return new WP_Error( 'pattern', sprintf(
                                __( '%s format is invalid', 'ltl-bookings' ),
                                $field->label
                            ) );
                        }
                        break;
                }
            }
        }

        return true;
    }

    /**
     * Save custom field values for appointment
     * 
     * @param int $appointment_id
     * @param array $custom_fields Field values
     * @return bool Success
     */
    public function save_field_values( int $appointment_id, array $custom_fields ): bool {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_appointment_fields';

        // Clear existing values
        $wpdb->delete( $table, [ 'appointment_id' => $appointment_id ], [ '%d' ] );

        // Insert new values
        foreach ( $custom_fields as $field_key => $value ) {
            if ( empty( $value ) ) continue;

            $wpdb->insert( $table, [
                'appointment_id' => $appointment_id,
                'field_key' => sanitize_key( $field_key ),
                'field_value' => sanitize_text_field( $value ),
                'created_at' => current_time( 'mysql' )
            ], [ '%d', '%s', '%s', '%s' ] );
        }

        return true;
    }

    /**
     * Get saved field values for appointment
     * 
     * @param int $appointment_id
     * @return array Field values
     */
    public function get_field_values( int $appointment_id ): array {
        global $wpdb;

        $table = $wpdb->prefix . 'ltlb_appointment_fields';

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT field_key, field_value FROM $table WHERE appointment_id = %d",
            $appointment_id
        ) );

        $values = [];
        foreach ( $results as $row ) {
            $values[ $row->field_key ] = $row->field_value;
        }

        return $values;
    }

    /**
     * Generate unique field key from label
     * 
     * @param string $label
     * @return string Field key
     */
    private function generate_field_key( string $label ): string {
        $key = sanitize_title( $label );
        $key = str_replace( '-', '_', (string) $key );
        
        // Ensure uniqueness
        global $wpdb;
        $table = $wpdb->prefix . 'ltlb_form_fields';
        
        $count = $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE field_key LIKE %s",
            $key . '%'
        ) );

        if ( $count > 0 ) {
            $key .= '_' . ( $count + 1 );
        }

        return $key;
    }

    /**
     * Check if field should be visible based on conditions
     * 
     * @param object $field Field config
     * @param array $form_values Current form values
     * @return bool Is visible
     */
    public function is_field_visible( object $field, array $form_values ): bool {
        if ( empty( $field->conditions ) ) {
            return true;
        }

        foreach ( $field->conditions as $condition ) {
            $target_field = $condition['field'] ?? '';
            $operator = $condition['operator'] ?? 'equals';
            $compare_value = $condition['value'] ?? '';
            
            $current_value = $form_values[ $target_field ] ?? '';

            $match = false;
            switch ( $operator ) {
                case 'equals':
                    $match = $current_value === $compare_value;
                    break;
                case 'not_equals':
                    $match = $current_value !== $compare_value;
                    break;
                case 'contains':
                    $match = strpos( (string) $current_value, (string) $compare_value ) !== false;
                    break;
                case 'empty':
                    $match = empty( $current_value );
                    break;
                case 'not_empty':
                    $match = ! empty( $current_value );
                    break;
            }

            if ( ! $match ) {
                return false;
            }
        }

        return true;
    }
}
