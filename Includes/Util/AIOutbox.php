<?php
if ( ! defined('ABSPATH') ) exit;

class LTLB_AI_Outbox {

	public static function is_hitl_mode(): bool {
		$ai_config = get_option( 'lazy_ai_config', [] );
		if ( ! is_array( $ai_config ) ) {
			$ai_config = [];
		}
		$mode = $ai_config['operating_mode'] ?? 'human-in-the-loop';
		return $mode === 'human-in-the-loop';
	}

	public static function is_ai_enabled(): bool {
		$ai_config = get_option( 'lazy_ai_config', [] );
		if ( ! is_array( $ai_config ) ) {
			$ai_config = [];
		}
		return ! empty( $ai_config['enabled'] );
	}

	public static function create_draft(
		string $action_type,
		string $ai_input = '',
		string $ai_output = '',
		array $metadata = [],
		string $notes = '',
		int $user_id = 0
	): int {
		global $wpdb;

		$table = $wpdb->prefix . 'lazy_ai_actions';
		$user_id = $user_id ?: get_current_user_id();
		$now = current_time( 'mysql' );

		$wpdb->insert(
			$table,
			[
				'created_at' => $now,
				'updated_at' => $now,
				'user_id' => (int) $user_id,
				'action_type' => $action_type,
				'status' => 'draft',
				'ai_input' => $ai_input,
				'ai_output' => $ai_output,
				'final_state' => null,
				'metadata' => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
				'notes' => $notes,
			],
			[ '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	public static function get_action( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	public static function list_actions( string $status = 'draft', int $limit = 50 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$limit = max( 1, min( 200, $limit ) );

		if ( $status === 'all' ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit ), ARRAY_A );
			return is_array( $rows ) ? $rows : [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
				$status,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : [];
	}

	public static function list_actions_by_type( string $action_type, string $status = 'all', int $limit = 50 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$limit = max( 1, min( 200, $limit ) );
		$action_type = sanitize_key( $action_type );

		if ( $status === 'all' ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE action_type = %s ORDER BY created_at DESC LIMIT %d", $action_type, $limit ),
				ARRAY_A
			);
			return is_array( $rows ) ? $rows : [];
		}

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE action_type = %s AND status = %s ORDER BY created_at DESC LIMIT %d",
				$action_type,
				$status,
				$limit
			),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : [];
	}

	public static function update_action_text( int $id, string $ai_output, string $notes = '' ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$now = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			[
				'ai_output' => $ai_output,
				'notes' => $notes,
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return $updated !== false;
	}

	public static function update_action_metadata( int $id, array $metadata, string $notes = '' ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$now = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			[
				'metadata' => ! empty( $metadata ) ? wp_json_encode( $metadata ) : null,
				'notes' => $notes,
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return $updated !== false;
	}

	public static function reject( int $id, int $user_id = 0 ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$user_id = $user_id ?: get_current_user_id();
		$now = current_time( 'mysql' );

		$updated = $wpdb->update(
			$table,
			[
				'status' => 'rejected',
				'approved_by' => (int) $user_id,
				'approved_at' => $now,
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return $updated !== false;
	}

	public static function approve_and_execute( int $id, int $user_id = 0 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$user_id = $user_id ?: get_current_user_id();
		$now = current_time( 'mysql' );

		$row = self::get_action( $id );
		if ( ! $row ) {
			return [ 'success' => false, 'message' => __( 'Action not found.', 'ltl-bookings' ) ];
		}

		$wpdb->update(
			$table,
			[
				'status' => 'approved',
				'approved_by' => (int) $user_id,
				'approved_at' => $now,
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return self::execute( $id );
	}

	public static function execute( int $id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'lazy_ai_actions';
		$row = self::get_action( $id );
		if ( ! $row ) {
			return [ 'success' => false, 'message' => __( 'Action not found.', 'ltl-bookings' ) ];
		}

		// In HITL mode, never execute drafts without explicit approval.
		$status = is_string( $row['status'] ?? null ) ? $row['status'] : '';
		if ( self::is_hitl_mode() && $status === 'draft' ) {
			return [ 'success' => false, 'message' => __( 'This draft must be approved before execution (HITL mode).', 'ltl-bookings' ) ];
		}
		if ( $status === 'executed' ) {
			return [ 'success' => false, 'message' => __( 'This action has already been executed.', 'ltl-bookings' ) ];
		}

		$result = self::execute_action_row( $row );
		$now = current_time( 'mysql' );

		if ( $result['success'] ) {
			$wpdb->update(
				$table,
				[
					'status' => 'executed',
					'executed_at' => $now,
					'final_state' => wp_json_encode( $result['final_state'] ?? [] ),
					'error_message' => null,
					'updated_at' => $now,
				],
				[ 'id' => $id ],
				[ '%s', '%s', '%s', '%s', '%s' ],
				[ '%d' ]
			);
			return [ 'success' => true, 'message' => $result['message'] ?? __( 'Executed.', 'ltl-bookings' ) ];
		}

		$wpdb->update(
			$table,
			[
				'status' => 'failed',
				'failed_at' => $now,
				'error_message' => $result['message'] ?? __( 'Execution failed.', 'ltl-bookings' ),
				'updated_at' => $now,
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return [ 'success' => false, 'message' => $result['message'] ?? __( 'Execution failed.', 'ltl-bookings' ) ];
	}

	public static function queue_or_execute_assign_room( int $appointment_id, int $resource_id, string $notes = '', int $user_id = 0 ): array {
		$appointment_id = (int) $appointment_id;
		$resource_id = (int) $resource_id;
		if ( $appointment_id <= 0 || $resource_id <= 0 ) {
			return [ 'success' => false, 'message' => __( 'Invalid appointment/resource.', 'ltl-bookings' ) ];
		}

		$metadata = [
			'appointment_id' => $appointment_id,
			'resource_id' => $resource_id,
		];
		$id = self::create_draft( 'assign_room', '', '', $metadata, $notes, $user_id );
		if ( ! $id ) {
			return [ 'success' => false, 'message' => __( 'Could not create outbox draft.', 'ltl-bookings' ) ];
		}

		if ( self::is_hitl_mode() ) {
			return [ 'success' => true, 'message' => __( 'Draft created in Outbox.', 'ltl-bookings' ), 'id' => $id ];
		}

		return self::approve_and_execute( $id );
	}

	public static function queue_or_execute_email(
		string $to,
		string $subject,
		string $body,
		string $ai_input = '',
		string $ai_output = '',
		array $metadata = [],
		int $user_id = 0
	): array {
		$metadata = array_merge(
			$metadata,
			[
				'to' => $to,
				'subject' => $subject,
				'body' => $body,
			]
		);

		$id = self::create_draft( 'email', $ai_input, $ai_output, $metadata, '', $user_id );
		if ( ! $id ) {
			return [ 'success' => false, 'message' => __( 'Could not create outbox draft.', 'ltl-bookings' ) ];
		}

		if ( self::is_hitl_mode() ) {
			return [ 'success' => true, 'message' => __( 'Draft created in Outbox.', 'ltl-bookings' ), 'id' => $id ];
		}

		// Autonomous: execute immediately.
		return self::approve_and_execute( $id );
	}

	private static function execute_action_row( array $row ): array {
		$action_type = is_string( $row['action_type'] ?? null ) ? $row['action_type'] : '';

		$meta_raw = $row['metadata'] ?? '';
		$metadata = [];
		if ( is_string( $meta_raw ) && $meta_raw !== '' ) {
			$decoded = json_decode( $meta_raw, true );
			if ( is_array( $decoded ) ) {
				$metadata = $decoded;
			}
		}

		if ( $action_type === 'email' ) {
			$to = isset( $metadata['to'] ) ? sanitize_email( (string) $metadata['to'] ) : '';
			$subject = isset( $metadata['subject'] ) ? sanitize_text_field( (string) $metadata['subject'] ) : '';
			$body = isset( $metadata['body'] ) ? wp_kses_post( (string) $metadata['body'] ) : '';

			if ( ! $to || ! is_email( $to ) ) {
				return [ 'success' => false, 'message' => __( 'Invalid email recipient.', 'ltl-bookings' ) ];
			}
			if ( $subject === '' || $body === '' ) {
				return [ 'success' => false, 'message' => __( 'Email subject/body missing.', 'ltl-bookings' ) ];
			}

			$sent = wp_mail( $to, $subject, $body );
			if ( ! $sent ) {
				return [ 'success' => false, 'message' => __( 'wp_mail failed.', 'ltl-bookings' ) ];
			}

			return [
				'success' => true,
				'message' => __( 'Email sent.', 'ltl-bookings' ),
				'final_state' => [
					'to' => $to,
					'subject' => $subject,
					'sent' => true,
				],
			];
		}

		if ( $action_type === 'assign_room' ) {
			$appointment_id = isset( $metadata['appointment_id'] ) ? intval( $metadata['appointment_id'] ) : 0;
			$resource_id = isset( $metadata['resource_id'] ) ? intval( $metadata['resource_id'] ) : 0;
			if ( $appointment_id <= 0 || $resource_id <= 0 ) {
				return [ 'success' => false, 'message' => __( 'Missing appointment_id/resource_id.', 'ltl-bookings' ) ];
			}
			if ( ! class_exists( 'LTLB_AppointmentResourcesRepository' ) ) {
				return [ 'success' => false, 'message' => __( 'Room assignment dependencies not loaded.', 'ltl-bookings' ) ];
			}
			$repo = new LTLB_AppointmentResourcesRepository();
			$ok = $repo->set_resource_for_appointment( $appointment_id, $resource_id );
			if ( ! $ok ) {
				return [ 'success' => false, 'message' => __( 'Could not assign room.', 'ltl-bookings' ) ];
			}
			return [
				'success' => true,
				'message' => __( 'Room assigned.', 'ltl-bookings' ),
				'final_state' => [ 'appointment_id' => $appointment_id, 'resource_id' => $resource_id ],
			];
		}

		if ( $action_type === 'insight_report' ) {
			// No side effects: approving/executing just marks it executed.
			return [
				'success' => true,
				'message' => __( 'Report stored.', 'ltl-bookings' ),
				'final_state' => [ 'stored' => true ],
			];
		}

		return [ 'success' => false, 'message' => __( 'No executor for this action type yet.', 'ltl-bookings' ) ];
	}
}
