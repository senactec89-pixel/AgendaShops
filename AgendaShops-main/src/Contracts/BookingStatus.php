<?php
declare(strict_types=1);

namespace AgendaShops\Contracts;

enum BookingStatus: string
{
    case PENDING_PARTNER_APPROVAL = 'PENDING_PARTNER_APPROVAL';
    case CONFIRMED = 'CONFIRMED';
    case REJECTED_BY_PARTNER = 'REJECTED_BY_PARTNER';
    case EXPIRED = 'EXPIRED';
    case CANCELED_BY_CUSTOMER = 'CANCELED_BY_CUSTOMER';
    case CANCELED_BY_PARTNER = 'CANCELED_BY_PARTNER';
    case IN_PROGRESS = 'IN_PROGRESS';
    case COMPLETED = 'COMPLETED';
    case NO_SHOW = 'NO_SHOW';
    case RESCHEDULED = 'RESCHEDULED';
}
