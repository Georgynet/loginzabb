<?php
/**
 * Created by PhpStorm.
 * User: Georg
 * Date: 14.10.2017
 * Time: 09:47
 */

namespace georgynet\loginzabb\controller;

use georgynet\loginzabb\model\LoginzaAPI;
use georgynet\loginzabb\model\LoginzaUserProfile;
use messenger;
use phpbb\db\driver\driver_interface;
use phpbb\config\config;
use phpbb\install\console\command\update\config\validate;
use phpbb\passwords\manager;
use phpbb\request\request;
use phpbb\request\request_interface;
use phpbb\template\template;
use phpbb\user;

class auth
{
    /** @var config */
    private $config;
    /** @var user */
    private $user;
    /** @var request */
    private $request;
    /** @var string */
    private $phpbbRootPath;
    /** @var string */
    private $phpExt;
    /** @var template */
    private $template;
    /** @var driver_interface */
    private $db;
    /** @var manager */
    private $passManager;

    public function __construct(config $config, user $user, request $request, template $template, driver_interface $db, manager $passManager, $phpbbRootPath, $phpExt)
    {
        $this->config = $config;
        $this->user = $user;
        $this->request = $request;
        $this->template = $template;
        $this->db = $db;
        $this->passManager = $passManager;
        $this->phpbbRootPath = $phpbbRootPath;
        $this->phpExt = $phpExt;
    }

    public function handle()
    {
        if ($this->config['require_activation'] == USER_ACTIVATION_DISABLE) {
            trigger_error('UCP_REGISTER_DISABLE');
        }

        $loginzaAPI = new LoginzaAPI();

        $profile = $loginzaAPI->getAuthInfo(
            $this->request->variable('token', '', true, request_interface::POST)
        );

        $userId = false;
        if (is_object($profile) && empty($profile->error_type)) {
            if (!($userId = $this->findUserByIdentity($profile->identity))) {
                $userId = $this->regUser($profile);
            }
        }

        $result = $this->user->session_create($userId, 0, 1);

        if ($result === true) {
            $redirect = $this->request->variable(
                'redirect',
                "{$this->phpbbRootPath}index.$this->phpExt"
            );

            $message = $this->user->lang['LOGIN_REDIRECT'];
            $l_redirect = (($redirect === "{$this->phpbbRootPath}index.$this->phpExt" || $redirect === "index.$this->phpExt") ? $this->user->lang['RETURN_INDEX'] : $this->user->lang['RETURN_PAGE']);

            $redirect = reapply_sid($redirect);

            if (defined('IN_CHECK_BAN') && $result['user_row']['user_type'] != USER_FOUNDER) {
                return;
            }

            $redirect = meta_refresh(3, $redirect);
            trigger_error($message . '<br /><br />' . sprintf($l_redirect, '<a href="' . $redirect . '">', '</a>'));
        }

        page_header($this->user->lang['LOGIN'], false);

        $this->template->set_filenames([
            'body' => 'login_body.html'
        ]);
        make_jumpbox(append_sid("{$this->phpbbRootPath}viewforum.$this->phpExt"));

        page_footer();
    }

    private function findUserByIdentity($identity)
    {
        $result = $this->db->sql_query("
			SELECT `user_id`
			FROM `" . USERS_TABLE . "`
			WHERE `loginza_identity` = '" . $this->db->sql_escape($identity) . "'
		");
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return (isset($row['user_id'])) ? $row['user_id'] : '';
    }

    /**
     * User registration.
     * @param \stdClass $profile
     * @return int
     */
    private function regUser($profile)
    {
        $loginzaProfile = new LoginzaUserProfile($profile);

        $newPassword = $loginzaProfile->genRandomPassword();

        $data = [
            'username' => utf8_normalize_nfc($loginzaProfile->getNickname()),
            'user_password' => $this->passManager->hash($newPassword),
            'user_email' => strtolower($profile->email),
            'user_birthday' => date('d-m-Y', strtotime($profile->dob)),
            'user_avatar' => (string) $profile->photo,
            'user_jabber' => (string) $profile->im->jabber,
            'user_timezone' => (float) $this->getTimezone(),
            'user_lang' => basename($this->user->lang_name),
            'user_type' => USER_NORMAL,
            'user_actkey' => '',
            'user_ip' => $this->user->ip,
            'user_regdate' => time(),
            'user_inactive_reason' => 0,
            'user_inactive_time' => 0,
            'loginza_identity' => $profile->identity,
            'loginza_provider' => $profile->provider
        ];

        $data['username'] = $this->getValidUsername($data);

        $error = $this->checkDnsbl();
        if (count($error)) {
            trigger_error(implode('', $error));
        }

        $groupId = $this->getGroupIdRegisteredUsers();
        if (!$groupId) {
            trigger_error('NO_GROUP');
        }
        $data['group_id'] = $groupId;

        if ($this->config['new_member_post_limit']) {
            $data['user_new'] = 1;
        }

        $userId = user_add($data);
        if ($userId === false) {
            trigger_error('NO_USER', E_USER_ERROR);
        }

        $this->sendMail($data, $newPassword);

        return $userId;
    }

    /**
     * Get valid username.
     * @param array $data
     * @return string
     */
    private function getValidUsername($data)
    {
        if (!function_exists('validate_data')) {
            require_once($this->phpbbRootPath . 'includes/functions_user.' . $this->phpExt);
        }

        $error = validate_data($data, [
            'username' => [
                ['string', false, $this->config['min_name_chars'], $this->config['max_name_chars']],
                ['username', '']
            ]
        ]);

        if (!count($error)) {
            return $data['username'];
        }
        
        $result = $this->db->sql_query("
            SELECT count(`user_id`) AS `count`
            FROM `" . USERS_TABLE . "`
            WHERE 1
        ");
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return LOGINZA_REGISTER_DEFAULT_LOGIN_PREFIX . $row['count'];
    }

    /**
     * Check DNS-based Blackhole List.
     * @return array
     */
    private function checkDnsbl()
    {
        if (!$this->config['check_dnsbl']) {
            return [];
        }
        
        if (($dnsbl = $this->user->check_dnsbl('register')) !== false) {
            return [
                sprintf($this->user->lang['IP_BLACKLISTED'], $this->user->ip, $dnsbl[1])
            ];
        }
        
        return [];
    }

    /**
     * Return Timezone.
     * @return float|string
     */
    private function getTimezone()
    {
        $timezone = date('Z') / 3600;
        $is_dst = date('I');

        if ($this->config['board_timezone'] == $timezone || $this->config['board_timezone'] == ($timezone - 1)) {
            $timezone = ($is_dst) ? $timezone - 1 : $timezone;

            if (!isset($this->user->lang['tz_zones'][(string)$timezone])) {
                $timezone = $this->config['board_timezone'];
            }
        } else {
            $timezone = $this->config['board_timezone'];
        }

        return $timezone;
    }

    /**
     * Send email.
     * @param array $data
     * @param string $newPassword
     */
    private function sendMail($data, $newPassword)
    {
        if ($this->config['email_enable']) {
            include_once($this->phpbbRootPath . 'includes/functions_messenger.' . $this->phpExt);

            $messenger = new messenger(false);

            $messenger->template('user_welcome', $data['lang']);

            $messenger->to($data['email'], $data['username']);

            $messenger->headers('X-AntiAbuse: Board servername - ' . $this->config['server_name']);
            $messenger->headers('X-AntiAbuse: User_id - ' . $this->user->data['user_id']);
            $messenger->headers('X-AntiAbuse: Username - ' . $this->user->data['username']);
            $messenger->headers('X-AntiAbuse: User IP - ' . $this->user->ip);

            $messenger->assign_vars([
                'WELCOME_MSG' => htmlspecialchars_decode(sprintf($this->user->lang['WELCOME_SUBJECT'], $this->config['sitename'])),
                'USERNAME' => htmlspecialchars_decode($data['username']),
                'PASSWORD' => htmlspecialchars_decode($newPassword)
            ]);

            $messenger->send(NOTIFY_EMAIL);
        }
    }

    /**
     * Return id group registered users.
     * @return int|false
     */
    private function getGroupIdRegisteredUsers()
    {
        $sql = 'SELECT group_id
            FROM ' . GROUPS_TABLE . "
            WHERE group_name = '" . $this->db->sql_escape('REGISTERED') . "'
                AND group_type = " . GROUP_SPECIAL;
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        return isset($row['group_id']) ? (int) $row['group_id'] : false;
    }
}
