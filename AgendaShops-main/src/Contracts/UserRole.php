<?php
declare(strict_types=1);

namespace AgendaShops\Contracts;

enum UserRole: string
{
    case CUSTOMER = 'CUSTOMER';
    case PARTNER_OWNER = 'PARTNER_OWNER';
    case PARTNER_STAFF = 'PARTNER_STAFF';
    case ADMIN = 'ADMIN';
}
