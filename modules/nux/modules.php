<?php

/**
 * NUX modules
 * @package modules
 * @subpackage nux
 * @todo filter/disable features depending on imap/pop3 module sets
 * @todo add links to the home page when no E-mail is set
 * @todo quick add for 20(?) or so top rss feeds
 * @todo add refresh token support
 */

if (!defined('DEBUG_MODE')) { die(); }

/**
 * @subpackage nux/handler
 * @todo unset nux_add_service_details
 */
class Hm_Handler_process_oauth2_authorization extends Hm_Handler_Module {
    public function process() {
        if (array_key_exists('state', $this->request->get) && $this->request->get['state'] == 'nux_authorization') {
            if (array_key_exists('code', $this->request->get)) {
                $details = $this->session->get('nux_add_service_details');
                $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
                $result = $oauth2->request_token($details['oauth2_token'], $this->request->get['code']);
                if (!empty($result) && array_key_exists('access_token', $result)) {
                    Hm_IMAP_List::add(array(
                        'name' => $details['name'],
                        'server' => $details['server'],
                        'port' => $details['port'],
                        'tls' => $details['tls'],
                        'user' => $details['email'],
                        'pass' => $result['access_token'],
                        'expiration' => strtotime(sprintf("+%d seconds", $result['expires_in'])),
                        'refresh_token' => $result['refresh_token'],
                        'refresh_url' => $details['refresh_token'],
                        'auth' => 'xoauth2'));

                    Hm_Msgs::add('E-mail account successfully added');
                    $servers = Hm_IMAP_List::dump(false, true);
                    $this->user_config->set('imap_servers', $servers);
                    Hm_IMAP_List::clean_up();
                    $user_data = $this->user_config->dump();
                    if (!empty($user_data)) {
                        $this->session->set('user_data', $user_data);
                    }
                    $this->session->record_unsaved('IMAP server added');
                    $this->session->secure_cookie($this->request, 'hm_reload_folders', '1');
                    $this->session->close_early();
                }
                else {
                    Hm_Msgs::add('ERRAn Error Occured');
                }
            }
            elseif (array_key_exists('error', $this->request->get)) {
                Hm_Msgs::add('ERR'.ucwords(str_replace('_', ' ', $this->request->get['error'])));
            }
            else {
                Hm_Msgs::add('ERRAn Error Occured');
            }
            $msgs = Hm_Msgs::get();
            $this->session->secure_cookie($this->request, 'hm_msgs', base64_encode(serialize($msgs)), 0);
            Hm_Router::page_redirect('?page=servers');
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_nux_add_service extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_pass', 'nux_service', 'nux_email'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);
            }
        }
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_setup_nux extends Hm_Handler_Module {
    public function process() {
        Nux_Quick_Services::oauth2_setup($this->config);
    }
}

/**
 * @subpackage nux/handler
 */
class Hm_Handler_process_nux_service extends Hm_Handler_Module {
    public function process() {
        list($success, $form) = $this->process_form(array('nux_service', 'nux_email'));
        if ($success) {
            if (Nux_Quick_Services::exists($form['nux_service'])) {
                $details = Nux_Quick_Services::details($form['nux_service']);
                $details['id'] = $form['nux_service'];
                $details['email'] = $form['nux_email'];
                if (array_key_exists('nux_account_name', $this->request->post) && trim($this->request->post['nux_account_name'])) {
                    $details['name'] = $this->request->post['nux_account_name'];
                }
                $this->out('nux_add_service_details', $details);
                $this->session->set('nux_add_service_details', $details);
            }
        }
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_dialog extends Hm_Output_Module {
    protected function output() {
        return '<div class="quick_add_section">'.
            '<div class="nux_step_one">'.
            $this->trans('Quickly add an account from popular E-mail providers. To manually configure an account, use the IMAP/SMTP/POP3 sections below.').
            '<br /><br /><label class="screen_reader" for="service_select">'.$this->trans('Select an E-mail provider').'</label>'.
            ' <select id="service_select" name="service_select"><option value="">'.$this->trans('Select an E-mail provider').'</option>'.Nux_Quick_Services::option_list(false, $this).'</select>'.
            '<label class="screen_reader" for="nux_username">'.$this->trans('Username').'</label>'.
            '<br /><input type="email" id="nux_username" class="nux_username" placeholder="'.$this->trans('Enter Your E-mail address').'" />'.
            '<label class="screen_reader" for="nux_account_name">'.$this->trans('Account Name').'</label>'.
            '<br /><input type="text" id="nux_account_name" class="nux_account_name" placeholder="'.$this->trans('Account Name [optional]').'" />'.
            '<br /><input type="button" class="nux_next_button" value="'.$this->trans('Next').'" />'.
            '</div><div class="nux_step_two"></div></div></div>';
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_filter_service_select extends Hm_Output_Module {
    protected function output() {
        $details = $this->get('nux_add_service_details', array());
        if (!empty($details)) {
            if (array_key_exists('auth', $details) && $details['auth'] == 'oauth2') {
                $this->out('nux_service_step_two',  oauth2_form($details, $this));
            }
            else {
                $this->out('nux_service_step_two',  credentials_form($details, $this));
            }
        }
    }
}

/**
 * @subpackage nux/output
 */
class Hm_Output_quick_add_section extends Hm_Output_Module {
    protected function output() {
        return '<div class="nux_add_account"><div data-target=".quick_add_section" class="server_section">'.
            '<img src="'.Hm_Image_Sources::$circle_check.'" alt="" width="16" height="16" /> '.
            $this->trans('Add An E-mail Account').'</div>';
    }
}

/**
 * @subpackage nux/functions
 */
function oauth2_form($details, $mod) {
    $oauth2 = new Hm_Oauth2($details['client_id'], $details['client_secret'], $details['redirect_uri']);
    $url = $oauth2->request_authorization_url($details['oauth2_authorization'], $details['scope'], 'nux_authorization', $details['email']);
    $res = '<input type="hidden" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div><div>';
    $res .= $mod->trans('This provider supports Oauth2 access to your account.');
    $res .= $mod->trans(' This is the most secure way to access your E-mail. Click "Enable" to be redirected to the provider site to allow access.');
    $res .= '</div><a class="enable_auth2" href="'.$url.'">'.$mod->trans('Enable').'</a>';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    return $res;
}

/**
 * @subpackage nux/functions
 */
function credentials_form($details, $mod) {
    $res = '<input type="hidden" id="nux_service" name="nux_service" value="'.$mod->html_safe($details['id']).'" />';
    $res .= '<input type="hidden" id="nux_email" name="nux_email" value="'.$mod->html_safe($details['email']).'" />';
    $res .= '<div class="nux_step_two_title">'.$mod->html_safe($details['name']).'</div>';
    $res .= $mod->trans('Enter your password for this E-mail provider to complete the connection process');
    $res .= '<br /><br /><input type="password" placeholder="'.$mod->trans('E-Mail Password').'" name="nux_password" class="nux_password" />';
    $res .= '<br /><input type="button" class="nux_submit" value="'.$mod->trans('Connect').'" /><br />';
    $res .= '<a href="" class="reset_nux_form">Reset</a>';
    return $res;
}

/**
 * @subpackage nux/lib
 */
class Nux_Quick_Services {

    static private $services = array();
    static private $oauth2 = array();

    static public function add($id, $details) {
        self::$services[$id] = $details;
    }
    static public function oauth2_setup($config) {
        $settings = array();
        $ini_file = rtrim($config->get('app_data_dir', ''), '/').'/oauth2.ini';
        if (is_readable($ini_file)) {
            $settings = parse_ini_file($ini_file, true);
            if (!empty($settings)) {
                foreach ($settings as $service => $vals) {
                    self::$services[$service]['client_id'] = $vals['client_id'];
                    self::$services[$service]['client_secret'] = $vals['client_secret'];
                    self::$services[$service]['redirect_uri'] = $vals['client_uri'];
                }
            }
        }
        self::$oauth2 = $settings;
    }

    static public function option_list($current, $mod) {
        $res = '';
        uasort(self::$services, function($a, $b) { return strcmp($a['name'], $b['name']); });
        foreach(self::$services as $id => $details) {
            $res .= '<option value="'.$mod->html_safe($id).'"';
            if ($id == $current) {
                $res .= ' selected="selected"';
            }
            $res .= '>'.$mod->trans($details['name']);
            $res .= '</option>';
        }
        return $res;
    }

    static public function exists($id) {
        return array_key_exists($id, self::$services);
    }

    static public function details($id) {
        if (array_key_exists($id, self::$services)) {
            return self::$services[$id];
        }
        return array();
    }
}

/**
 * todo move the urls to the oauth2 file
 */
Nux_Quick_Services::add('gmail', array(
    'server' => 'imap.gmail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Gmail',
    'auth' => 'oauth2',
    'oauth2_authorization' => 'https://accounts.google.com/o/oauth2/auth',
    'oauth2_token' => 'https://www.googleapis.com/oauth2/v3/token',
    'refresh_token' => 'https://www.googleapis.com/oauth2/v3/token',
    'scope' => ' https://mail.google.com/'
));

Nux_Quick_Services::add('outlook', array(
    'server' => 'imap-mail.outlook.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Outlook',
    'auth' => 'oauth2',
    'oauth2_authorization' => 'https://login.live.com/oauth20_authorize.srf',
    'oauth2_token' => 'https://login.live.com/oauth20_token.srf',
    'scope' => 'wl.imap'
));

Nux_Quick_Services::add('yahoo', array(
    'server' => 'imap.mail.yahoo.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Yahoo',
    'auth' => 'login'
));

Nux_Quick_Services::add('mailcom', array(
    'server' => 'imap.mail.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Mail.com',
    'auth' => 'login'
));

Nux_Quick_Services::add('aol', array(
    'server' => 'imap.aol.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'AOL',
    'auth' => 'login'
));

Nux_Quick_Services::add('gmx', array(
    'server' => 'imap.gmx.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 143,
    'name' => 'GMX',
    'auth' => 'login'
));

Nux_Quick_Services::add('zoho', array(
    'server' => 'imap.zoho.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Zoho',
    'auth' => 'login'
));

Nux_Quick_Services::add('fastmail', array(
    'server' => 'mail.messagingengine.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Fastmail',
    'auth' => 'login'
));

Nux_Quick_Services::add('yandex', array(
    'server' => 'imap.yandex.com',
    'type' => 'imap',
    'tls' => true,
    'port' => 993,
    'name' => 'Yandex',
    'auth' => 'login'
));

?>
