<?php
namespace Wpmet\Libs;

defined( 'ABSPATH' ) || exit;

if(!class_exists('\Wpmet\Libs\Banner')):

class Banner {

    protected $script_version = '2.2.0';

    protected $key = 'wpmet_banner';
    protected $data;
    protected $last_check;
    protected $check_interval = (3600 * 6);

    protected $plugin_screens;

    protected $text_domain;
    protected $filter_string;
    protected $filter_array = [];
    protected $api_url;

    /**
     * Content types the remote feed is allowed to ask us to render.
     */
    const ALLOWED_TYPES = ['banner', 'notice'];

    /**
     * Max length for values that end up as option/transient/user-meta keys.
     */
    const MAX_KEY_LENGTH = 64;


    public function get_version(){
        return $this->script_version;
    }

    public function get_script_location(){
        return __FILE__;
    }

    /**
     * URL schemes accepted from the remote feed.
     *
     * Deliberately narrower than wp_allowed_protocols() -- a promo banner has
     * no reason to emit mailto:, tel: or feed: links.
     */
    public static function allowed_protocols() {
        return ['http', 'https'];
    }

    /**
     * HTML the remote feed is allowed to emit.
     *
     * Intentionally different from \MetForm\Utils\Util::kses(): no iframe, no
     * form elements, no data-* passthrough, but style allowed on every tag so
     * campaign headlines keep their formatting. Everything here arrives from a
     * remote endpoint, so it is untrusted input -- if that endpoint is ever
     * compromised the worst it can produce is broken markup, never script
     * execution. Event handler attributes (on*) are dropped automatically
     * because wp_kses() strips every attribute not listed.
     */
    public static function allowed_html() {

        $common = [
            'class' => [],
            'style' => [],
            'title' => [],
        ];

        return [
            'a'      => array_merge($common, ['href' => [], 'target' => [], 'rel' => []]),
            'abbr'   => $common,
            'b'      => $common,
            'br'     => [],
            'div'    => $common,
            'em'     => $common,
            'h1'     => $common,
            'h2'     => $common,
            'h3'     => $common,
            'h4'     => $common,
            'h5'     => $common,
            'h6'     => $common,
            'i'      => $common,
            'img'    => array_merge($common, ['src' => [], 'alt' => [], 'width' => [], 'height' => []]),
            'li'     => $common,
            'ol'     => $common,
            'p'      => $common,
            'small'  => $common,
            'span'   => $common,
            'strong' => $common,
            'u'      => $common,
            'ul'     => $common,
        ];
    }

	public function call(){
        add_action( 'admin_head', [$this, 'display_content'] );
    }

    public function display_content(){
        $this->get_data();

        if(empty($this->data)) {
            return;
        }

        $screen = get_current_screen();

        if(is_null($screen)) {
            return;
        }

        if(!class_exists('\Oxaim\Libs\Notice')) {
            return;
        }

        foreach($this->data as $content) {

            if(!empty($this->filter_array) && $this->in_blacklist($content, $this->filter_array)) {
                continue;
            }

            if($content->start > time() || time() > $content->end) {
                continue;
            }

            if(!$this->is_correct_screen_to_show($content->screen, $screen->id)) {
                continue;
            }

            $inline_css = '';
            $banner_unique_id = ($content->data->unique_key !== '' ? $content->data->unique_key : $content->id);

            if($content->data->style_css !== '') {
                $inline_css = ' style="' . esc_attr($content->data->style_css) . '"';
            }

            $instance = \Oxaim\Libs\Notice::instance('wpmet-jhanda', $banner_unique_id)
            ->set_dismiss('global', (3600 * 24 * 15));

            if($content->type == 'banner'){
                $this->init_banner($content, $instance, $inline_css);
            }

            if($content->type == 'notice'){
                $this->init_notice($content, $instance, $inline_css);
            }
        }
    }


    private function init_notice($content, $instance, $inline_css){

        $instance->set_message($content->data->notice_body);

        if($content->data->notice_image !== ''){
            $instance->set_logo($content->data->notice_image);
        }

        if($content->data->button_text !== '' && $content->data->button_link !== ''){
            $instance->set_button([
                'default_class' => 'button',
                'class' => 'button-secondary button-small', // button-primary button-secondary button-small button-large button-link
                'text' => $content->data->button_text,
                'url' => $content->data->button_link,
            ]);
        }
        $instance->call();
    }

    private function init_banner($content, $instance, $inline_css){

        if($content->data->banner_link === '' || $content->data->banner_image === ''){
            return;
        }

        $html = sprintf(
            '<a target="_blank" rel="noopener noreferrer"%1$s class="wpmet-jhanda-href" href="%2$s"><img style="display: block;margin: 0 auto;" src="%3$s" alt="%4$s" /></a>',
            $inline_css, // already escaped in display_content()
            esc_url($content->data->banner_link, self::allowed_protocols()),
            esc_url($content->data->banner_image, self::allowed_protocols()),
            esc_attr($content->title)
        );

        $instance->set_gutter(false)
        ->set_html($html)
        ->call();
    }


	private function in_whitelist($conf, $list) {

		$match = $conf->data->whitelist;

		if(empty($match)) {
			return true;
		};

		$match_arr = explode(',', $match);

		foreach($list as $word) {
			if(in_array($word, $match_arr)) {
				return true;
			}
		}

		return false;
	}


	private function in_blacklist($conf, $list) {

		$match = $conf->data->blacklist;

		if(empty($match)) {
			return false;
		};

		$match_arr = explode(',', $match);

		foreach($match_arr as $idx => $item) {

			$match_arr[$idx] = trim($item);
		}

		foreach($list as $word) {
			if(in_array($word, $match_arr)) {
				return true;
			}
		}

		return false;
	}


    public function is_test($is_test = false) {
        if($is_test === true){
            $this->check_interval = 1;
        }

        return $this;
    }


    public function set_text_domain($text_domain) {
        $this->text_domain = $text_domain;

        return $this;
    }


    public function set_filter($filter_string) {
        $this->filter_string = $filter_string;
		if(!empty($filter_string)) {

			$filter = explode(',', $this->filter_string);

			foreach ($filter as $id => $item) {
				$this->filter_array[$id] = trim($item);
			}
		}

        return $this;
    }


    public function set_api_url($url) {
        $this->api_url = $url;

        return $this;
    }

    public function set_plugin_screens($screen) {
        $this->plugin_screens[] = $screen;

        return $this;
    }


    /**
     * Normalise one remote feed entry into a known-shape, fully escaped object.
     *
     * Everything the feed sends that is not on this list is dropped, so a
     * poisoned response cannot introduce new fields for later code to trip on.
     *
     * @param  mixed $content Raw decoded entry.
     * @return object|null    Sanitized entry, or null if it is not renderable.
     */
    private function sanitize_content($content) {

        if(!is_object($content) && !is_array($content)) {
            return null;
        }

        $content = (object) $content;

        $type = isset($content->type) ? sanitize_key((string) $content->type) : '';

        if(!in_array($type, self::ALLOWED_TYPES, true)) {
            return null;
        }

        $data = (isset($content->data) && (is_object($content->data) || is_array($content->data))) ? (object) $content->data : new \stdClass();

        $item         = new \stdClass();
        $item->id     = isset($content->id) ? $this->sanitize_id($content->id) : '';
        $item->title  = isset($content->title) ? sanitize_text_field((string) $content->title) : '';
        $item->type   = $type;
        $item->screen = isset($content->screen) ? sanitize_key((string) $content->screen) : '';
        $item->start  = isset($content->start) ? intval($content->start) : 0;
        $item->end    = isset($content->end) ? intval($content->end) : 0;

        $item->data = (object) [
            'unique_key'   => isset($data->unique_key) ? $this->sanitize_id($data->unique_key) : '',
            'style_css'    => isset($data->style_css) ? $this->sanitize_style($data->style_css) : '',
            'blacklist'    => isset($data->blacklist) ? sanitize_text_field((string) $data->blacklist) : '',
            'whitelist'    => isset($data->whitelist) ? sanitize_text_field((string) $data->whitelist) : '',
            'banner_link'  => isset($data->banner_link) ? $this->sanitize_url($data->banner_link) : '',
            'banner_image' => isset($data->banner_image) ? $this->sanitize_url($data->banner_image) : '',
            'notice_body'  => isset($data->notice_body) ? $this->sanitize_html($data->notice_body) : '',
            'notice_image' => isset($data->notice_image) ? $this->sanitize_url($data->notice_image) : '',
            'button_text'  => isset($data->button_text) ? sanitize_text_field((string) $data->button_text) : '',
            'button_link'  => isset($data->button_link) ? $this->sanitize_url($data->button_link) : '',
        ];

        if($item->id === '' && $item->data->unique_key === '') {
            return null;
        }

        return $item;
    }


    /**
     * Run every entry of a decoded feed through sanitize_content().
     */
    private function sanitize_response($response) {

        if(!is_object($response) && !is_array($response)) {
            return [];
        }

        $clean = [];

        foreach((array) $response as $content) {

            $item = $this->sanitize_content($content);

            if(!is_null($item)) {
                $clean[] = $item;
            }
        }

        return $clean;
    }


    /**
     * Values that become HTML ids, transient names and user-meta keys.
     */
    private function sanitize_id($value) {

        if(!is_scalar($value)) {
            return '';
        }

        return substr(sanitize_key((string) $value), 0, self::MAX_KEY_LENGTH);
    }


    /**
     * Inline CSS from the feed, filtered through core's CSS property allowlist.
     */
    private function sanitize_style($css) {

        if(!is_scalar($css) || (string) $css === '') {
            return '';
        }

        return (string) safecss_filter_attr((string) $css);
    }


    /**
     * Links and image sources from the feed. Anything that is not plain
     * http(s) -- javascript:, data:, protocol-relative tricks -- comes back
     * as an empty string and the caller skips rendering it.
     *
     * The explicit scheme requirement matters beyond protocol filtering:
     * esc_url_raw() prepends http:// to a bare string, so an attribute-
     * breakout attempt like `x" onerror="..."` would otherwise survive as a
     * loadable URL pointing wherever the feed liked. Every URL the endpoint
     * actually serves is already absolute https, so nothing legitimate is lost.
     */
    private function sanitize_url($url) {

        if(!is_scalar($url)) {
            return '';
        }

        $url = trim((string) $url);

        if(!preg_match('#^https?://#i', $url)) {
            return '';
        }

        return esc_url_raw($url, self::allowed_protocols());
    }


    /**
     * Rich-text bodies from the feed.
     */
    private function sanitize_html($html) {

        if(!is_scalar($html)) {
            return '';
        }

        return wp_kses((string) $html, self::allowed_html(), self::allowed_protocols());
    }


    private function get_data() {

        // Sanitize on read as well as on write: installs that already cached an
        // unsanitized (or poisoned) payload get cleaned up on the next render
        // without waiting for the refresh interval.
        $this->data = $this->sanitize_response(get_option($this->text_domain . '__banner_data'));

        $this->last_check = get_option($this->text_domain . '__banner_last_check');
        $this->last_check = $this->last_check == '' ? 0 : $this->last_check;

        if(($this->check_interval + $this->last_check) >= time()){
            return;
        }

        $response = wp_remote_get( $this->api_url . '/cache/'.$this->text_domain.'.json?nocache='.time(),
            [
                'timeout'     => 10,
                'httpversion' => '1.1',
            ]
        );

        // Record the attempt whatever the outcome. The old code only stamped
        // last_check after a successful, non-empty response, so an endpoint
        // that was down or slow meant a 10 second blocking request on every
        // single admin page load.
        update_option($this->text_domain . '__banner_last_check', time());

        if(is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)){
            return;
        }

        $decoded = json_decode(wp_remote_retrieve_body($response));

        if(JSON_ERROR_NONE !== json_last_error()){
            return;
        }

        // An empty-but-valid response is accepted and stored. That is what
        // makes a bad payload revocable: serving [] from the endpoint clears
        // it everywhere instead of leaving the last cached copy in place.
        $this->data = $this->sanitize_response($decoded);

        update_option($this->text_domain . '__banner_data', $this->data);
    }


    public function is_correct_screen_to_show($b_screen, $screen_id) {

        if(in_array($b_screen, [$screen_id, 'all_page'])) {
            return true;
        }


        if($b_screen == 'plugin_page') {
            return in_array($screen_id, (array) $this->plugin_screens);
        }

        return false;
    }

	private static $instance;

	public static function instance($text_domain = '') {

        self::$instance = new static();
        return self::$instance->set_text_domain($text_domain);
    }
}

endif;
