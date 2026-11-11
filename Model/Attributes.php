<?php
/**
 * @copyright Copyright (c) 2019, WEXO A/S
 */

namespace Partner\Module\Model;

class Attributes
{
    const USER_IP = 'partnerads_user_ip';
    const PARTNER_ID = 'partnerads_partner_id';
    const ORDER_SENT = 'partnerads_order_sent';
    const PACID = 'partnerads_pacid_id';

    /**
     * Values of partnerads_order_sent. NULL means the order has no partner data.
     */
    const ORDER_SENT_PENDING = 0;   // has partner data, not sent yet
    const ORDER_SENT_DONE = 1;      // sent to Partner-ads (HTTP 2xx)
    const ORDER_SENT_DRY_RUN = 2;   // Development mode: logged only, never sent and never exported later
}
