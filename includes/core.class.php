<?php
namespace RedBrick;

/**
 * @package RedBrick
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 1.0
 */
final class Core extends BasePlugin {
    /**
     * @see parent::finishLaunching()
     * @since 1.0
     */
    protected function finishLaunching() {
        if (! $this->verifyWordPressCompatibility() ) {
            return false;
        }

        $this->initDB();

        if ( $this->isUninstalling ) {
            return true;
        }

        add_action( 'init', array( $this, 'pluginDidFinishLaunching' ) );
        
        return true;
    }

    /**
     * @see parent::pluginDidFinishLaunching()
     * @since 1.0
     */
    public function pluginDidFinishLaunching() {
        global $pagenow;

        $this->verifyVersionOfStoredData();

        switch ( $pagenow ) {
            case 'index.php':
                if ( is_admin() ) {
                    add_action( 'wp_dashboard_setup', array( $this, 'wpDidSetupDashboard' ) );
                }
                break;

            case 'wp-comments-post.php':
                add_filter( 'preprocess_comment', array( $this, 'wpWillProcessComment' ), 1 );
                break;
        }
    }

    /**
     * Verifies that the data stored into the database are compatible with 
     * this version of the plugin and if needed invokes the upgrader.
     *
     * @since 1.0
     * @return bool
     */
    private function verifyVersionOfStoredData() {
        $current_version = $this->db->getOption( 'version' );
        
        if ( $current_version === $this->version ) {
            return true;
        }

        $now = time();

        if ( $current_version ) {
            // Invoke Upgrader.
        }
        else {
            $this->db->setOption( 'installed_on', $now );
        }

        $this->db->setOption( 'last_updated', $now );
        $this->db->setOption( 'version', $this->version );
        
        return true;
    }

    /**
     * @since 1.0
     *
     * @param array $comment_data
     * @return array
     */
    public function wpWillProcessComment( $comment_data ) {
        $this->load( 'includes/comment-validator.class.php' );

        $validator = new CommentValidator( $comment_data );

        try {
            $validation_id = $validator->validateComment();
        } catch ( \Exception $e ) {
            $this->updateCounter( 'spam' );
            $this->goBack();
        }

        $this->updateCounter( $validation_id );

        switch ( $validation_id ) {
            case 'spam':
                $this->goBack( $comment_data['comment_post_ID'] );
                break;

            case 'moderate':
                // Forces the comment to be held in the moderation queue.
                add_filter( 'pre_comment_approved', function() { return 0; } );
                break;
        }

        add_action( 'wp_insert_comment', array( $this, 'wpDidInsertComment' ) );

        return $comment_data;
    }

    /**
     * @since 1.0
     * @param string $count_id
     */
    private function updateCounter( $counter_id ) {
        $count = (int) $this->db->getOption( $counter_id, 0, 'counters' );

        if ( $count < 0 ) {
            $count = 0;
        }

        $this->db->setOption( $counter_id, ++$count, 'counters' );
        $this->db->setOption( 'last_processed_time', time() );
        $this->db->setOption( 'last_processed_comment_id', 0 );
    }

    /**
     * @since 1.0.3
     * @param int $id
     */
    public function wpDidInsertComment( $id ) {
        $id = (int) $id;

        if ( $id > 0 ) {
            $this->db->setOption( 'last_processed_comment_id', $id );
        }
    }

    /**
     * @since 1.0
     * @param int $post_id
     */
    private function goBack( $post_id = 0 ) {
        if ( isset( $_SERVER['HTTP_REFERER'] ) ) {
            $redirect_url = $_SERVER['HTTP_REFERER'];
        }
        else {
            $post         = ( ( $post_id > 0 ) ? get_post( $post_id ) : null );
            $redirect_url = ( $post ? get_permalink( $post ) : home_url() );
        }

        wp_safe_redirect( esc_url_raw( $redirect_url ) );

        exit;
    }

    /**
     * @since 1.0
     */
    public function wpDidSetupDashboard() {
        wp_add_dashboard_widget( 'redbrick_counters', 'RedBrick', array( $this, 'showCounters' ) );
    }

    /**
     * @since 1.0
     * @return bool
     */
    public function showCounters() {
        $counters = (array) $this->db->getOption( 'counters', array() );

        if (! $counters ) {
            echo '<p>', __( 'RedBrick has yet to process its first comment.' ), '</p>';

            return true;
        }

        foreach ( array( 'approved', 'moderate', 'spam' ) as $key ) {
            $counters[$key] = ( isset( $counters[$key] ) ? (int) $counters[$key] : 0 );
        }

        echo '<p>';

        printf( 
            __( '%1$s comments were blocked, %2$s were held in the moderation queue, while %3$s passed checks.', 'redbrick' ),
            "<strong>{$counters['spam']}</strong>",
            "<strong>{$counters['moderate']}</strong>", 
            "<strong>{$counters['approved']}</strong>"
        );

        echo '</p>'; 
        
        $gm_time = (int) $this->db->getOption( 'last_processed_time' );

        if ( $gm_time ) {
            $format    = get_option( 'date_format' ) . ' @ ' . get_option( 'time_format' );
            $time      = $gm_time + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
            $date_time = '<em>' . date_i18n( $format, $time ) . '</em>';

            $comment_id      = (int) $this->db->getOption( 'last_processed_comment_id' );
            $comment_id_text = ( $comment_id > 0 ) ? ( '(#' . $comment_id . ')' ) : '';

            echo '<p><small>';

            printf( __( 'The last comment %1$s was processed on %2$s.', 'redbrick' ), $comment_id_text, $date_time );

            echo '</small></p>';
        }

        return true;
    }
}
?>