<?php
/**
 * Created by PhpStorm.
 * User: Georg
 * Date: 14.10.2017
 * Time: 08:46
 */

namespace georgynet\loginzabb\event;

use phpbb\controller\helper;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
    /** @var template */
    private $template;
    /** @var helper */
    private $helper;

    /**
     * @param template $template
     * @param helper $helper
     */
    public function __construct(template $template, helper $helper)
    {
        $this->template = $template;
        $this->helper = $helper;
    }

    /**
     * @inheritdoc
     */
    public static function getSubscribedEvents()
    {
        return [
            'core.page_header_after' => 'widget',
        ];
    }

    public function widget()
    {
        $this->template->assign_vars([
            'LOGINZA_RETURN_URL' => urlencode(
                append_sid(
                    generate_board_url() . $this->helper->route(
                        'georgynet_loginzabb_auth'
                    )
                )
            ),
        ]);
    }
}
