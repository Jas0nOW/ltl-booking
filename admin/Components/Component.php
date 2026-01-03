<?php
/**
 * LTLB Admin Component Library
 * 
 * Premium Agency-Level UI Components
 * Provides reusable, accessible, and beautifully styled admin components.
 *
 * @since 1.0.0
 * @since 3.0.0 Agency-level redesign with premium styling
 */

class LTLB_Admin_Component {

    /**
     * Render the start of a premium card component.
     *
     * @param string $title   The title of the card.
     * @param array  $options Optional. Options: 'style', 'class', 'padding', 'icon', 'actions'
     */
    public static function card_start( string $title = '', array $options = [] ): void {
        $style = isset( $options['style'] ) ? 'style="' . esc_attr( $options['style'] ) . '"' : '';
        $class = 'ltlb-card';
        $class .= isset( $options['class'] ) ? ' ' . esc_attr( $options['class'] ) : '';
        if ( isset( $options['padding'] ) && $options['padding'] === false ) {
            $class .= ' ltlb-card--no-padding';
        }
        
        echo '<div class="' . esc_attr( $class ) . '" ' . $style . '>';
        
        if ( ! empty( $title ) ) {
            echo '<div class="ltlb-card__header">';
            echo '<h3 class="ltlb-card__title">';
            if ( ! empty( $options['icon'] ) ) {
                echo '<span class="dashicons ' . esc_attr( $options['icon'] ) . '" aria-hidden="true"></span>';
            }
            echo esc_html( $title );
            echo '</h3>';
            
            if ( ! empty( $options['actions'] ) ) {
                echo '<div class="ltlb-card__actions">' . wp_kses_post( $options['actions'] ) . '</div>';
            }
            
            echo '</div>';
        }
        
        echo '<div class="ltlb-card__body">';
    }

    /**
     * Render the end of a card component.
     */
    public static function card_end(): void {
        echo '</div></div>'; // Close body and card
    }

    /**
     * Render a choice tile.
     *
     * @param string  $name        The name attribute for the radio input.
     * @param string  $value       The value attribute for the radio input.
     * @param string  $label       The label for the choice tile.
     * @param string  $description The description for the choice tile.
     * @param boolean $checked     Whether the tile should be checked by default.
     * @param string  $icon        Optional dashicon class
     */
    public static function choice_tile( string $name, string $value, string $label, string $description, bool $checked = false, string $icon = '' ): void {
        ?>
        <label class="ltlb-choice-tile <?php echo $checked ? 'is-checked' : ''; ?>">
            <input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" <?php checked( $checked ); ?>>
            <div class="ltlb-choice-tile__check">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
            </div>
            <?php if ( ! empty( $icon ) ): ?>
            <div class="ltlb-choice-tile__icon">
                <span class="dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
            </div>
            <?php endif; ?>
            <div class="ltlb-choice-tile__content">
                <div class="ltlb-choice-tile__label"><?php echo esc_html( $label ); ?></div>
                <div class="ltlb-choice-tile__description"><?php echo esc_html( $description ); ?></div>
            </div>
        </label>
        <?php
    }

    /**
     * Render a premium stat card for dashboards.
     * 
     * @param array $options Options: 'value', 'label', 'icon', 'trend', 'trend_label', 'color'
     */
    public static function stat_card( array $options ): void {
        $color = $options['color'] ?? 'primary';
        ?>
        <div class="ltlb-stat-card ltlb-stat-card--<?php echo esc_attr( $color ); ?>">
            <?php if ( ! empty( $options['icon'] ) ): ?>
            <div class="ltlb-stat-card__icon">
                <span class="dashicons <?php echo esc_attr( $options['icon'] ); ?>" aria-hidden="true"></span>
            </div>
            <?php endif; ?>
            <div class="ltlb-stat-card__content">
                <div class="ltlb-stat-card__value"><?php echo esc_html( $options['value'] ?? '0' ); ?></div>
                <div class="ltlb-stat-card__label"><?php echo esc_html( $options['label'] ?? '' ); ?></div>
                <?php if ( isset( $options['trend'] ) ): 
                    $trend_class = $options['trend'] >= 0 ? 'positive' : 'negative';
                ?>
                <div class="ltlb-stat-card__trend ltlb-stat-card__trend--<?php echo esc_attr( $trend_class ); ?>">
                    <span class="dashicons dashicons-arrow-<?php echo $options['trend'] >= 0 ? 'up' : 'down'; ?>-alt" aria-hidden="true"></span>
                    <?php echo abs( $options['trend'] ); ?>%
                    <?php if ( ! empty( $options['trend_label'] ) ): ?>
                    <span class="ltlb-stat-card__trend-label"><?php echo esc_html( $options['trend_label'] ); ?></span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render the start of a table toolbar.
     */
    public static function toolbar_start(): void {
        echo '<div class="ltlb-toolbar">';
    }

    /**
     * Render the end of a table toolbar.
     */
    public static function toolbar_end(): void {
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
    public static function empty_state(string $title, string $message, string $button_label = '', string $button_url = '', string $icon = 'dashicons-layout'): void {
        ?>
        <div class="ltlb-empty-state">
            <div class="ltlb-empty-state__icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>" aria-hidden="true"></span>
            </div>
            <h3 class="ltlb-empty-state__title"><?php echo esc_html($title); ?></h3>
            <p class="ltlb-empty-state__message"><?php echo esc_html($message); ?></p>
            <?php if ( ! empty($button_label) && ! empty($button_url) ): ?>
                <a href="<?php echo esc_url($button_url); ?>" class="ltlb-btn ltlb-btn--primary">
                    <span class="dashicons dashicons-plus-alt2" aria-hidden="true"></span>
                    <?php echo esc_html($button_label); ?>
                </a>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render an alert/notice component.
     * 
     * @param string $message The message to display.
     * @param string $type    Type: 'info', 'success', 'warning', 'danger'
     * @param array  $options Options: 'title', 'dismissible', 'icon'
     */
    public static function alert( string $message, string $type = 'info', array $options = [] ): void {
        $icons = [
            'info'    => 'dashicons-info',
            'success' => 'dashicons-yes-alt',
            'warning' => 'dashicons-warning',
            'danger'  => 'dashicons-dismiss',
        ];
        $icon = $options['icon'] ?? ( $icons[ $type ] ?? 'dashicons-info' );
        ?>
        <div class="ltlb-alert ltlb-alert--<?php echo esc_attr( $type ); ?>" role="alert">
            <span class="ltlb-alert__icon dashicons <?php echo esc_attr( $icon ); ?>" aria-hidden="true"></span>
            <div class="ltlb-alert__content">
                <?php if ( ! empty( $options['title'] ) ): ?>
                <div class="ltlb-alert__title"><?php echo esc_html( $options['title'] ); ?></div>
                <?php endif; ?>
                <div class="ltlb-alert__message"><?php echo wp_kses_post( $message ); ?></div>
            </div>
            <?php if ( ! empty( $options['dismissible'] ) ): ?>
            <button type="button" class="ltlb-alert__dismiss" aria-label="<?php echo esc_attr__( 'Dismiss', 'ltl-bookings' ); ?>">
                <span class="dashicons dashicons-no-alt" aria-hidden="true"></span>
            </button>
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
        <div class="ltlb-wizard-nav" role="navigation" aria-label="<?php echo esc_attr__( 'Setup Progress', 'ltl-bookings' ); ?>">
            <?php foreach ($steps as $index => $title): 
                $step_num = $index + 1;
                $class = 'ltlb-wizard-nav__step';
                $aria_current = '';
                if ($step_num === $current_step) {
                    $class .= ' is-active';
                    $aria_current = 'aria-current="step"';
                } elseif ($step_num < $current_step) {
                    $class .= ' is-complete';
                }
            ?>
                <div class="<?php echo esc_attr($class); ?>" <?php echo $aria_current; ?>>
                    <div class="ltlb-wizard-nav__bubble">
                        <?php if ($step_num < $current_step): ?>
                        <span class="dashicons dashicons-yes" aria-hidden="true"></span>
                        <?php else: ?>
                        <?php echo $step_num; ?>
                        <?php endif; ?>
                    </div>
                    <div class="ltlb-wizard-nav__label"><?php echo esc_html($title); ?></div>
                    <?php if ($step_num < count($steps)): ?>
                    <div class="ltlb-wizard-nav__connector" aria-hidden="true"></div>
                    <?php endif; ?>
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
            $save_label = __( 'Complete Setup', 'ltl-bookings' );
        }
        ?>
        <div class="ltlb-wizard-nav-buttons">
            <?php if (!$is_first): ?>
            <button type="button" class="ltlb-btn ltlb-btn--secondary ltlb-wizard-prev">
                <span class="dashicons dashicons-arrow-left-alt" aria-hidden="true"></span>
                <?php echo esc_html__('Back', 'ltl-bookings'); ?>
            </button>
            <?php endif; ?>
            
            <?php if (!$is_last): ?>
            <button type="button" class="ltlb-btn ltlb-btn--primary ltlb-wizard-next">
                <?php echo esc_html__('Continue', 'ltl-bookings'); ?>
                <span class="dashicons dashicons-arrow-right-alt" aria-hidden="true"></span>
            </button>
            <?php else: ?>
            <button type="submit" name="submit" class="ltlb-btn ltlb-btn--primary ltlb-btn--lg">
                <span class="dashicons dashicons-yes-alt" aria-hidden="true"></span>
                <?php echo esc_html($save_label); ?>
            </button>
            <?php endif; ?>
        </div>
        </div>
        <?php
    }

    /**
     * Renders pagination links with premium styling.
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
        
        $start_item = ( $current_page - 1 ) * $current_per_page + 1;
        $end_item = min( $current_page * $current_per_page, $total_items );

        ?>
        <div class="ltlb-pagination-wrapper">
            <div class="ltlb-pagination__info">
                <?php 
                printf(
                    /* translators: %1$d = start item, %2$d = end item, %3$d = total items */
                    esc_html__( 'Showing %1$d-%2$d of %3$d', 'ltl-bookings' ),
                    $start_item,
                    $end_item,
                    $total_items
                );
                ?>
            </div>
            
            <div class="ltlb-pagination__controls">
                <label for="ltlb-per-page-select" class="screen-reader-text">
                    <?php echo esc_html__('Items per page', 'ltl-bookings'); ?>
                </label>
                <select id="ltlb-per-page-select" class="ltlb-pagination__per-page" onchange="window.location.href='<?php echo esc_url($base_url); ?>&per_page=' + this.value">
                    <?php foreach ([20, 50, 100] as $option): ?>
                    <option value="<?php echo esc_attr($option); ?>" <?php selected($current_per_page, $option); ?>>
                        <?php echo esc_html($option); ?> <?php echo esc_html__('per page', 'ltl-bookings'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($total_pages > 1): ?>
            <nav class="ltlb-pagination" role="navigation" aria-label="<?php echo esc_attr__('Pagination', 'ltl-bookings'); ?>">
                <?php if ($current_page > 1): ?>
                <a href="<?php echo esc_url(add_query_arg(['paged' => $current_page - 1, 'per_page' => $current_per_page], $base_url)); ?>" class="ltlb-pagination__item ltlb-pagination__prev" aria-label="<?php echo esc_attr__('Previous page', 'ltl-bookings'); ?>">
                    <span class="dashicons dashicons-arrow-left-alt2" aria-hidden="true"></span>
                </a>
                <?php endif; ?>
                
                <?php
                // Show page numbers with ellipsis
                $show_pages = [];
                $show_pages[] = 1;
                for ($i = max(2, $current_page - 1); $i <= min($total_pages - 1, $current_page + 1); $i++) {
                    $show_pages[] = $i;
                }
                if ($total_pages > 1) {
                    $show_pages[] = $total_pages;
                }
                $show_pages = array_unique($show_pages);
                sort($show_pages);
                
                $last_page = 0;
                foreach ($show_pages as $page_num):
                    if ($last_page && $page_num > $last_page + 1):
                ?>
                <span class="ltlb-pagination__ellipsis">…</span>
                <?php
                    endif;
                    $last_page = $page_num;
                ?>
                <a href="<?php echo esc_url(add_query_arg(['paged' => $page_num, 'per_page' => $current_per_page], $base_url)); ?>" 
                   class="ltlb-pagination__item <?php echo $page_num === $current_page ? 'is-active' : ''; ?>"
                   <?php echo $page_num === $current_page ? 'aria-current="page"' : ''; ?>>
                    <?php echo $page_num; ?>
                </a>
                <?php endforeach; ?>
                
                <?php if ($current_page < $total_pages): ?>
                <a href="<?php echo esc_url(add_query_arg(['paged' => $current_page + 1, 'per_page' => $current_per_page], $base_url)); ?>" class="ltlb-pagination__item ltlb-pagination__next" aria-label="<?php echo esc_attr__('Next page', 'ltl-bookings'); ?>">
                    <span class="dashicons dashicons-arrow-right-alt2" aria-hidden="true"></span>
                </a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a button group for consistent button styling.
     * 
     * @param array $buttons Array of button configs with 'label', 'url' or 'type', 'variant', 'icon'
     */
    public static function button_group( array $buttons ): void {
        echo '<div class="ltlb-btn-group">';
        foreach ( $buttons as $btn ) {
            $variant = $btn['variant'] ?? 'secondary';
            $icon = ! empty( $btn['icon'] ) ? '<span class="dashicons ' . esc_attr( $btn['icon'] ) . '" aria-hidden="true"></span>' : '';
            
            if ( ! empty( $btn['url'] ) ) {
                echo '<a href="' . esc_url( $btn['url'] ) . '" class="ltlb-btn ltlb-btn--' . esc_attr( $variant ) . '">';
                echo $icon . esc_html( $btn['label'] );
                echo '</a>';
            } else {
                $type = $btn['type'] ?? 'button';
                $name = ! empty( $btn['name'] ) ? ' name="' . esc_attr( $btn['name'] ) . '"' : '';
                echo '<button type="' . esc_attr( $type ) . '"' . $name . ' class="ltlb-btn ltlb-btn--' . esc_attr( $variant ) . '">';
                echo $icon . esc_html( $btn['label'] );
                echo '</button>';
            }
        }
        echo '</div>';
    }
}