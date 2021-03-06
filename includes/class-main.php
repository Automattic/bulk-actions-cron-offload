<?php
/**
 * Plugin's main class, dispatcher for specific bulk-edit requests
 *
 * @package Bulk_Edit_Cron_Offload
 */

namespace Automattic\WP\Bulk_Edit_Cron_Offload;

/**
 * Class Main
 */
class Main extends Singleton {
	/**
	 * Requested action
	 *
	 * @var string
	 */
	private $action = null;

	/**
	 * Posts to process
	 *
	 * @var array
	 */
	private $posts = null;

	/**
	 * Taxonomy terms to add
	 *
	 * @var array
	 */
	private $tax_input = null;

	/**
	 * Post author to set
	 *
	 * @var int
	 */
	private $post_author = null;

	/**
	 * Comment status to set
	 *
	 * @var string
	 */
	private $comment_status = null;

	/**
	 * Ping status to set
	 *
	 * @var string
	 */
	private $ping_status = null;

	/**
	 * New post status
	 *
	 * @var string
	 */
	private $post_status = null;

	/**
	 * Posts' sticky status
	 *
	 * @var int
	 */
	private $post_sticky = null;

	/**
	 * Posts' format
	 *
	 * @var string
	 */
	private $post_format = null;

	/**
	 * Register action
	 */
	public function class_init() {
		add_action( 'load-edit.php', array( $this, 'intercept' ) );
	}

	/**
	 * Call appropriate handler
	 */
	public function intercept() {
		// Nothing to do.
		if ( ! isset( $_REQUEST['action'] ) ) {
			return;
		}

		// Parse request to determine what to do.
		$this->populate_vars();

		// Now what?
		switch ( $this->action ) {
			case 'delete_all':
				break;

			case 'trash':
				break;

			case 'untrash':
				break;

			case 'delete':
				break;

			case 'edit':
				break;

			// How did you get here?
			default:
				return;
				break;
		}
	}

	/**
	 * Capture relevant variables to be stored in cron events
	 */
	private function populate_vars() {
		$this->action = $_REQUEST['action'];

		if ( isset( $_REQUEST['post'] ) && is_array( $_REQUEST['post'] ) ) {
			$this->posts = array_map( 'absint', $_REQUEST['post'] );
		}

		if ( isset( $_REQUEST['tax_input'] ) && is_array( $_REQUEST['tax_input'] ) ) {
			$this->tax_input = $_REQUEST['tax_input'];
		}

		if ( isset( $_REQUEST['post_author'] ) && -1 === (int) $_REQUEST['post_author'] ) {
			$this->post_author = $_REQUEST['post_author'];
		}

		if ( isset( $_REQUEST['comment_status'] ) && ! empty( $_REQUEST['comment_status'] ) ) {
			$this->comment_status = $_REQUEST['comment_status'];
		}

		if ( isset( $_REQUEST['ping_status'] ) && ! empty( $_REQUEST['ping_status'] ) ) {
			$this->ping_status = $_REQUEST['ping_status'];
		}

		if ( isset( $_REQUEST['_status'] ) && -1 !== (int) $_REQUEST['_status'] ) {
			$this->post_status = $_REQUEST['_status'];
		}

		if ( isset( $_REQUEST['sticky'] ) && -1 !== (int) $_REQUEST['sticky'] ) {
			$this->post_sticky = $_REQUEST['sticky'];
		}

		if ( isset( $_REQUEST['post_format'] ) && -1 !== (int) $_REQUEST['post_format'] ) {
			$this->post_format = $_REQUEST['post_format'];
		}

		// Stop Core from processing bulk request.
		unset( $_REQUEST['action'] );
		unset( $_REQUEST['action2'] );
	}
}

Main::instance();
