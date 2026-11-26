<?php
/**
 * Uninstall handler. Club data is kept unless opt-in removal is added (phase 1).
 *
 * @package AirsoftClub
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'acm_version' );
