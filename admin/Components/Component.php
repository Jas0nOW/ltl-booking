<?php
/**
 * LTLB Admin Component
 *
 * This class is responsible for rendering various admin components
 * for the LTLB plugin.
 */

class LTLB_Admin_Component {
    /**
     * Render a choice tile.
     *
     * @param string  $name        The name attribute for the radio input.
     * @param string  $value       The value attribute for the radio input.
     * @param string  $label       The label for the choice tile.
     * @param string  $description The description for the choice tile.
     * @param boolean $checked     Whether the tile should be checked by default.
     */
    public static function choice_tile( string $name, string $value, string $label, string $description, bool $checked = false ): void {
        ?>
        <label class="ltlb-choice-tile <?php echo $checked ? 'is-checked' : ''; ?>">
            <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $checked ); ?>>
            <div class="ltlb-choice-tile__content">
                <div class="ltlb-choice-tile__label"><?php echo esc_html( $label ); ?></div>
                <div class="ltlb-choice-tile__description"><?php echo esc_html( $description ); ?></div>
            </div>
        </label>
        <?php
    }

    /**
     * Render the start of a table toolbar.
     */
    public static function toolbar_start(): void {
        echo '<div class="ltlb-table-toolbar">';
    }

    /**
     * Render the end of a table toolbar.
     */
    public static function toolbar_end(): void {
        echo '</div>';
    }

    /**
     * Render the start of a card component.
     *
     * @param string $title   The title of the card.
     * @param array  $options Optional. An array of additional options for the card.
     *                       'style' => 'background-color: #f00;', // Example inline style
     *                       'class' => 'my-custom-class',
     *                       'padding' => false
     */
    public static function card_start( string $title = '', array $options = [] ): void {
        $style = isset( $options['style'] ) ? 'style="' . esc_attr( $options['style'] ) . '"' : '';
        $class = isset( $options['class'] ) ? ' ' . esc_attr( $options['class'] ) : '';
        if ( isset( $options['padding'] ) && $options['padding'] === false ) {
            $class .= ' ltlb-card--no-padding';
        }
        echo '<div class="ltlb-card' . $class . '" ' . $style . '>';
        if ( ! empty( $title ) ) {
            echo '<div class="ltlb-card__title">' . esc_html( $title ) . '</div>';
        }
    }

    /**
     * Render the end of a card component.
     */
    public static function card_end(): void {
        echo '</div>';
    }

    /**
     * Renders a styled empty state component.
     *
     * @param string $title The main title of the empty state.
     * @param string $message The descriptive message.
     * @param string $button_label Optional. The label for the call-to-action button.
     * @param string $button_url Optional. The URL for the call-to-action button.
     * @param string $icon Optional. A dashicon class.
     */
    public static function empty_state(string $title, string $message, string $button_label = '', string $button_url = '', string $icon = 'dashicons-info-outline'): void {
        ?>
        <div class="ltlb-empty-state">
            <div class="ltlb-empty-state__icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>
            <h3 class="ltlb-empty-state__title"><?php echo esc_html($title); ?></h3>
            <p class="ltlb-empty-state__message"><?php echo esc_html($message); ?></p>
            <?php if ( ! empty($button_label) && ! empty($button_url) ): ?>
                <a href="<?php echo esc_url($button_url); ?>" class="ltlb-btn ltlb-btn--primary">
                    <?php echo esc_html($button_label); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Renders the navigation steps for a wizard.
     *
     * @param array $steps Array of step titles.
     * @param int $current_step The currently active step (1-based).
     */
    public static function wizard_steps(array $steps, int $current_step): void {
        ?>
        <div class="ltlb-wizard-nav">
            <?php foreach ($steps as $index => $title): 
                $step_num = $index + 1;
                $class = 'ltlb-wizard-nav__step';
                if ($step_num === $current_step) {
                    $class .= ' is-active';
                } elseif ($step_num < $current_step) {
                    $class .= ' is-complete';
                }
            ?>
                <div class="<?php echo esc_attr($class); ?>">
                    <div class="ltlb-wizard-nav__bubble"><?php echo $step_num; ?></div>
                    <div class="ltlb-wizard-nav__label"><?php echo esc_html($title); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }

    /**
     * Renders the start of a wizard step container.
     *
     * @param int $step_num The step number (1-based).
     */
    public static function wizard_step_start(int $step_num): void {
        echo '<div class="ltlb-wizard-step" data-step="' . esc_attr($step_num) . '">';
    }

    /**
     * Renders the end of a wizard step container, including navigation buttons.
     *
     * @param bool $is_first Whether this is the first step.
     * @param bool $is_last Whether this is the last step.
     * @param string $save_label The label for the save button (should be pre-translated).
     */
    public static function wizard_step_end(bool $is_first = false, bool $is_last = false, string $save_label = ''): void {
        if ( empty( $save_label ) ) {
            $save_label = __( 'Save', 'ltl-bookings' );
        }
        echo '<div class="ltlb-wizard-nav-buttons">';
        if (!$is_first) {
            echo '<button type="button" class="ltlb-btn ltlb-btn--secondary ltlb-wizard-prev">' . esc_html__('Back', 'ltl-bookings') . '</button>';
        }
        if (!$is_last) {
            echo '<button type="button" class="ltlb-btn ltlb-btn--primary ltlb-wizard-next">' . esc_html__('Next', 'ltl-bookings') . '</button>';
        } else {
            submit_button($save_label, 'primary', 'submit', false);
        }
        echo '</div></div>';
    }

    /**
     * Renders pagination links.
     *
     * @param int $total_items Total number of items.
     * @param int $per_page Number of items per page.
     */
    public static function pagination(int $total_items, int $per_page): void {
        $total_pages = ceil($total_items / $per_page);
        if ($total_pages <= 1 && $total_items <= 20) {
            return;
        }

        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $base_url = remove_query_arg(['paged', 'per_page']);
        $current_per_page = isset($_GET['per_page']) ? max(20, intval($_GET['per_page'])) : $per_page;

        echo '<div class="ltlb-pagination-wrapper">';
        
        // Items per page dropdown
        echo '<div class="ltlb-pagination-per-page">';
        echo '<label for="ltlb-per-page-select">' . esc_html__('Items per page:', 'ltl-bookings') . '</label> ';
        echo '<select id="ltlb-per-page-select" onchange="window.location.href=\'' . esc_url($base_url) . '&per_page=\' + this.value">';
        foreach ([20, 50, 100] as $option) {
            $selected = ($current_per_page == $option) ? ' selected' : '';
            echo '<option value="' . esc_attr($option) . '"' . $selected . '>' . esc_html($option) . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Page links
        if ($total_pages > 1) {
            $links = paginate_links([
                'base' => $base_url . '%_%&per_page=' . $current_per_page,
                'format' => '&paged=%#%',
                'current' => $current_page,
                'total' => $total_pages,
                'prev_text' => __('&laquo; Prev', 'ltl-bookings'),
                'next_text' => __('Next &raquo;', 'ltl-bookings'),
                'type' => 'array',
            ]);

            if (is_array($links)) {
                echo '<div class="ltlb-pagination">';
                foreach ($links as $link) {
                    echo $link;
                }
                echo '</div>';
            }
        }
        
        echo '</div>';
    }
}