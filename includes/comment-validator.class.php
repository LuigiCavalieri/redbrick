<?php
namespace RedBrick;

/**
 * @package RedBrick
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 *
 * @since 1.0
 */
class CommentValidator {
    /**
     * @since 1.0
     */
    const MIN_ABS_SCORE = -10;

    /**
     * @since 1.0
     */
    const MAX_NUM_URLS = 7;
    
    /**
     * @since 1.0
     * @var int
     */
    private $score = 0;
    
    /**
     * @since 1.0
     * @var string
     */
    private $name;
    
    /**
     * @since 1.0
     * @var string
     */
    private $email;

    /**
     * @since 1.0
     * @var string
     */
    private $websiteURL;
    
    /**
     * @since 1.0
     * @var string
     */
    private $IP;
    
    /**
     * @since 1.0
     * @var array
     */
    private $content = array();
    
    /**
     * @since 1.0
     * @var array
     */
    private $URLs = array();

    /**
     * @since 1.0
     * @var int
     */
    private $numOfURLs = 0;

    /**
     * @since 1.0
     * @param array $comment_data
     */
    public function __construct( &$comment_data ) {
        $this->name           = $comment_data['comment_author'];
        $this->email          = $comment_data['comment_author_email'];
        $this->websiteURL     = $comment_data['comment_author_url'];
        $this->IP             = ( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
        $this->content['raw'] = str_replace( '\\', '', $comment_data['comment_content'] );
    }

    /**
     * @since 1.0
     * @return string
     */
    public function validateComment() {
        $this->detectURLs();
        $this->checkWebsiteURL();
        $this->checkNameAndEmail();
        $this->checkKeywords();
        $this->checkContent();
        $this->checkAuthorHistory();

        if ( $this->score > 0 ) {
            return 'approved';
        }

        if ( $this->score < -3 ) {
            return 'spam';
        }
        
        return 'moderate';
    }

    /**
     * @since 1.0
     * @param int $addend
     */
    private function updateScore( $addend ) {
        $this->score += (int) $addend;

        if ( $this->score <= self::MIN_ABS_SCORE ) {
            $this->discardComment();
        }
    }

    /**
     * @since 1.0
     */
    private function discardComment() {
        throw new \Exception( 'Comment is rubbish.' );
    }
    
    /**
     * @since 1.0
     * @return bool
     */
    private function detectURLs() {
        // Extracted from make_clickable()
        // The regex is a non-anchored pattern and does not have a single fixed starting character.
        $pattern = '~                                        
            [\\w]{1,20}+://                            # Scheme and hier-part prefix
            (?=\S{1,2000}\s)                           # Limit to URLs less than about 2000 characters long
            [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]*+     # Non-punctuation URL character
            (?:                                        # Unroll the Loop: Only allow puctuation URL character if followed by a non-punctuation URL character
                [\'.,;:!?)]                            # Punctuation URL character
                [\\w\\x80-\\xff#%\\~/@\\[\\]*(+=&$-]++ # Non-punctuation URL character
            )*
        ~xS';
        
        $replace_and_collect_callback = function( $matches ) {
            $this->URLs[] = $matches[0];
            
            return '%REDBRICK_URL%';
        };
        
        $this->content['with_placeholders'] = preg_replace_callback(
            $pattern,
            $replace_and_collect_callback,
            " {$this->content['raw']} ", // Padding with whitespaces makes the regex straighter.
            self::MAX_NUM_URLS,
            $this->numOfURLs
        );
        
        if ( $this->numOfURLs <= 1 ) {
            $this->updateScore( 2 );
        }
        elseif ( $this->numOfURLs == self::MAX_NUM_URLS ) {
            $this->discardComment();
        
            return false;
        }

        $this->content['plain'] = strip_tags( $this->content['with_placeholders'] );

        if ( $this->numOfURLs === 0 ) {
            return true;
        }

        $cleanup_callback = function( $matches ) { return ''; };

        $this->content['plain'] = preg_replace_callback(
            '/%REDBRICK_URL%/',
            $cleanup_callback,
            $this->content['plain'],
            -1,
            $num_of_plain_urls
        );

        $num_of_hyperlinks = $this->numOfURLs - $num_of_plain_urls;

        if ( $num_of_hyperlinks ) {
            $this->updateScore( -5 * $num_of_hyperlinks );

            // Looks for hyperlinks with more than one attribute.
            if ( preg_match( '#<a\s+([a-z]+=\s*"[^"]*"\s*){2,}>#i', $this->content['raw'] ) ) {
                $this->updateScore( -5 );
            }
        }
        elseif ( $this->numOfURLs > 1 ) {
            $this->updateScore( -$this->numOfURLs );
        }

        foreach ( $this->URLs as $url ) {
            if ( strlen( $url ) > 80 ) {
                $this->updateScore( -1 );
            }
            elseif ( $this->isShortURL( $url ) ) {
                $this->updateScore( -5 );
            }
            
            $this->doURLCheckup( $url );
        }
        
        return true;
    }

    /**
     * @since 1.0
     * 
     * @param string $url
     * @return bool
     */
    private function isShortURL( $url ) {
        if ( strlen( $url ) > 35 ) {
            return false;
        }

        $components = parse_url( $url );

        if ( 
            isset( $components['user'] ) || isset( $components['pass'] ) || 
            isset( $components['port'] ) || isset( $components['query'] ) || 
            isset( $components['fragment'] )
        ) {
            $this->updateScore( -1 );

            return false;
        }

        if (! isset( $components['path'] ) ) {
            return false;
        }

        $path_parts       = explode( '/', $components['path'] );
        $path_parts_count = count( $path_parts );

        if ( $path_parts_count > 2 ) {
           return false; 
        }

        // Checks the last path part.
        if ( 
            ( strlen( $path_parts[$path_parts_count-1] ) > 15 ) || 
            preg_match( '#[^0-9a-zA-Z]+#', $path_parts[$path_parts_count-1] ) ) {
            return false;
        }

        $host = $this->getHostFromURLComponents( $components );

        if ( strlen( $host ) > 10 ) {
            return false;
        }

        return true;
    }

    /**
     * @since 1.0
     * 
     * @param array $components
     * @return string
     */
    private function getHostFromURLComponents( $components ) {
        if ( isset( $components['host'] ) ) {
            return $components['host'];
        }
        
        $first_slash_pos = strpos( $components['path'], '/' );

        if ( $first_slash_pos === false ) {
            return $components['path'];
        }
        
        return substr( $components['path'], 0, $first_slash_pos );
    }

    /**
     * @since 1.0.4
     * @param string $url
     */
    private function doURLCheckup( $url ) {
        // Checks TLD length.
        if (! preg_match( '#://.+\.[a-z]{2,10}#i', $url ) ) {
            $this->discardComment();
        }

        // Entrusted TLD?
        if ( preg_match( '#://.+\.(?:pl|jp|cn|info|ly|st|site|online)#i', $url ) ) {
            $this->updateScore( -1 );
        }
        
        // Does it contain uncommon characters?
        if ( preg_match( '#[^.:_a-zA-Z0-9/-]+#', $url ) ) {
            $this->updateScore( -1 );
        }
    }

    /**
     * @since 1.0
     * @return bool
     */
    private function checkWebsiteURL() {
        if (! $this->websiteURL ) {
            return false;
        }

        if ( $this->isShortURL( $this->websiteURL ) ) {
            $this->discardComment();
        }

        $old_score = $this->score;

        $this->doURLCheckup( $this->websiteURL );

        $components = parse_url( $this->websiteURL );

        if ( isset( $components['host'] ) && isset( $components['path'] ) ) {
            $this->updateScore( -2 );
        }

        $host = $this->getHostFromURLComponents( $components );

        foreach ( $this->URLs as $url ) {
            if ( $url == $this->websiteURL ) {
                $this->updateScore( -5 );
            }
            elseif ( stripos( $url, $host ) !== false ) {
                $this->updateScore( -2 );
            }
        }

        if ( $old_score == $this->score ) {
            $this->updateScore( 1 );

            return true;
        }

        return false;
    }

    /**
     * @since 1.0
     * @return bool
     */
    private function checkNameAndEmail() {
        $old_score = $this->score;

        if ( strlen( $this->name ) > 25 ) {
            $this->updateScore( -1 );
        }

        if ( strlen( $this->email ) > 50 ) {
            $this->updateScore( -1 );
        }

        $name_and_email = $this->name . ' ' . $this->email;
        $num_matches    = (int) preg_match_all( '/[0-9]/', $name_and_email );
        
        if ( $num_matches > 4 ) {
            $this->updateScore( 4 - $num_matches );
        }

        $num_matches = (int) preg_match_all( '/\./', $this->email );
        
        if ( $num_matches > 3 ) {
            $this->updateScore( 3 - $num_matches );
        }

        if (
            // Name doesn't have at least one latin vowel.
            !preg_match( '/[aeiou]+/i', $this->name ) ||
            
            // Name contains uncommon charaters.
            preg_match( '/[^._\p{Ll}\p{Lu} ]+/u', $this->name )
        ) {
            $this->updateScore( -2 );
        }

        // Checking email address format.
        $pattern = '/^[._+a-z0-9-]+@[a-z0-9-]+\.[.a-z0-9-]+$/i';

        if (! preg_match( $pattern, $this->email ) ) {
            $this->updateScore( -5 );
        }

        if ( $old_score == $this->score ) {
            $this->updateScore( 2 );

            return true;
        }

        return false;
    }
    
    
    /**
     * @since 1.0
     */
    private function checkKeywords() {
        $escaped_name = preg_quote( $this->name, '#' );
        
        if ( preg_match( "#<a\s+[^>]*>{$escaped_name}</a>#i", $this->content['raw'] ) ) {
            $this->updateScore( -5 );
        }
    }
    
    /**
     * @since 1.0
     */
    private function checkContent() {
        $word_count = str_word_count( $this->content['plain'] );
        
        if ( $word_count <= 30 ) {
            if ( $this->numOfURLs > 0 ) {
                if ( $word_count <= 15 ) {
                    $this->updateScore( -2 * $this->numOfURLs );
                }
                else {
                    $this->updateScore( -1 * $this->numOfURLs ); 
                }
            }
            elseif ( $word_count <= 15 ) {
                $this->updateScore( -1 );
            }
        }
        elseif ( $word_count < 200 ) {
            $this->updateScore( 2 );
        }
        else {
            $this->updateScore( -5 );
        }
        
        // Looking for quartet of consonants.
        $all_comment_components = $this->name . ' ' . $this->email . ' ' . $this->websiteURL . ' ' . $this->content['raw'];
        $num_matches            = (int) preg_match_all( '/[b-df-hj-np-tv-xz]{4}/i', $all_comment_components );
        
        $this->updateScore( -$num_matches );

        // Checking for "poor beginning".
        $keywords = array( 'nice', 'cool', 'neat', 'terrific', 'wow'  );
        
        foreach ( $keywords as $keyword ) {
            $pos = stripos( $this->content['plain'], $keyword );
            
            if ( ( $pos !== false ) && ( $pos < 11 ) ) {
                $this->updateScore( -5 );
                
                break;
            }
        }
    }
    
    /**
     * @since 1.0
     * @return bool
     */
    private function checkAuthorHistory() {
        global $wpdb;
        
        $where = '( comment_author = %s AND comment_author_email = %s )';
        
        if ( $this->IP ) {
            $where .= ' OR comment_author_IP = %s';
        }
        
        $query = $wpdb->prepare(
            "SELECT comment_approved AS 'id', COUNT( comment_approved ) AS 'count'
             FROM {$wpdb->comments}
             WHERE {$where}
             GROUP BY comment_approved",
            $this->name, $this->email, $this->IP
        );

        $history = $wpdb->get_results( $query );
        
        if (! $history ) {
            return false;
        }
            
        foreach ( $history as $status ) {
            switch ( $status->id ) {
                case 1:
                    $this->updateScore( $status->count );
                    break;

                case 'spam':
                    if ( $status->count > 0 ){
                        $this->updateScore( 1 - 2 * $status->count );
                    }
                    break;

                case 'trash':
                    if ( $status->count > 0 ) {
                        $this->updateScore( -pow( $count, $count - 1 ) );
                    }
                    break;
            }
        }

        return true;
    }
}
?>