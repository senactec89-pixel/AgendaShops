<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Bookings;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Modules\Catalog\CatalogService;
use AgendaShops\Domain\{Input, Problem};

final class BookingService
{
    public const LABELS = ['PENDING_PARTNER_APPROVAL'=>'Aguardando parceiro','CONFIRMED'=>'Confirmado','REJECTED_BY_PARTNER'=>'Recusado pelo parceiro','EXPIRED'=>'Expirado','CANCELED_BY_CUSTOMER'=>'Cancelado pelo cliente','CANCELED_BY_PARTNER'=>'Cancelado pelo parceiro','IN_PROGRESS'=>'Em atendimento','COMPLETED'=>'Concluído','NO_SHOW'=>'Não compareceu','RESCHEDULED'=>'Reagendado'];
    public function __construct(public readonly Database $db, private CatalogService $catalog) {}

    public function professional(string $serviceId, string $professionalId): array
    {
        $row = $this->db->one('SELECT p.* FROM professionals p JOIN service_professionals sp ON sp.professional_id=p.id WHERE sp.service_id=? AND p.id=? AND p.active=1', [$serviceId, $professionalId]);
        if (!$row) { throw new Problem('Profissional indisponível para este serviço.'); }
        return $row;
    }
    public function available(array $service, array $professional, string $start, string $end, ?string $ignoreId = null): bool
    {
        $business = $this->db->one("SELECT * FROM businesses WHERE id=? AND status='APPROVED'", [$service['business_id']]);
        if (!$business || $start <= Database::now() || $start >= $end) { return false; }
        $zone = new \DateTimeZone($business['timezone']);
        $local = (new \DateTimeImmutable($start))->setTimezone($zone);
        $localEnd = (new \DateTimeImmutable($end))->setTimezone($zone);
        if ($local->format('Y-m-d') !== $localEnd->format('Y-m-d')) { return false; }
        foreach ([$business, $professional] as $schedule) {
            if (!in_array($local->format('N'), explode(',', $schedule['weekdays']), true) || $local->format('H:i') < $schedule['opens'] || $localEnd->format('H:i') > $schedule['closes']) { return false; }
        }
        if ($this->db->one('SELECT id FROM schedule_blocks WHERE professional_id=? AND starts_at<? AND ends_at>?', [$professional['id'], $end, $start])) { return false; }
        return !$this->db->one("SELECT id FROM bookings WHERE professional_id=? AND starts_at<? AND ends_at>? AND status IN ('CONFIRMED','IN_PROGRESS') AND id<>?", [$professional['id'], $end, $start, $ignoreId ?? '']);
    }
    public function slots(string $serviceId, string $date, ?string $professionalId = null): array
    {
        $service = $this->catalog->publicService($serviceId);
        $zone = new \DateTimeZone($service['timezone']);
        $day = Input::date($date . 'T00:00', $zone);
        if ($day < (new \DateTimeImmutable('today', $zone)) || $day > new \DateTimeImmutable('+180 days', $zone)) { throw new Problem('Escolha uma data nos próximos 180 dias.'); }
        $professionals = $this->db->all('SELECT p.* FROM professionals p JOIN service_professionals sp ON sp.professional_id=p.id WHERE sp.service_id=? AND p.active=1 ORDER BY p.name', [$serviceId]);
        $business = $this->db->one('SELECT * FROM businesses WHERE id=?',[$service['business_id']]);
        $dayStart = $day->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $dayEnd = $day->modify('+1 day')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $slots = [];
        foreach ($professionals as $professional) {
            if ($professionalId !== null && $professional['id'] !== $professionalId) { continue; }
            if (!in_array($day->format('N'),explode(',',$professional['weekdays']),true) || !in_array($day->format('N'),explode(',',$business['weekdays']),true)) { continue; }
            $busy = $this->db->all('SELECT starts_at,ends_at FROM schedule_blocks WHERE professional_id=? AND starts_at<? AND ends_at>?',[$professional['id'],$dayEnd,$dayStart]);
            $busy = array_merge($busy,$this->db->all("SELECT starts_at,ends_at FROM bookings WHERE professional_id=? AND status IN('CONFIRMED','IN_PROGRESS') AND starts_at<? AND ends_at>?",[$professional['id'],$dayEnd,$dayStart]));
            for ($minute = 0; $minute < 1440; $minute += 15) {
                $startDate = $day->modify('+' . $minute . ' minutes');
                $endDate = $startDate->modify('+' . (int)$service['duration_minutes'] . ' minutes');
                $start = $startDate->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
                $end = $endDate->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
                if ($start <= Database::now() || $startDate->format('H:i') < max($professional['opens'],$business['opens']) || $endDate->format('H:i') > min($professional['closes'],$business['closes']) || $endDate->format('Y-m-d') !== $day->format('Y-m-d')) { continue; }
                $conflict = false;
                foreach ($busy as $interval) { if ($interval['starts_at'] < $end && $interval['ends_at'] > $start) { $conflict = true; break; } }
                if ($conflict) { continue; }
                $slots[] = ['professional_id' => $professional['id'], 'professional_name' => $professional['name'], 'local_start' => $startDate->format('Y-m-d\TH:i'), 'time' => $startDate->format('H:i'), 'starts_at' => $start];
            }
        }
        usort($slots,fn($a,$b)=>strcmp($a['starts_at'],$b['starts_at']) ?: strcmp($a['professional_name'],$b['professional_name']));
        return $slots;
    }
    public function request(array $user, array $data): string
    {
        $serviceId = Input::text($data, 'service_id');
        $professionalId = Input::text($data, 'professional_id');
        $key = Input::text($data, 'request_key', 16, 100);
        return $this->db->transaction(function () use ($user, $data, $serviceId, $professionalId, $key): string {
            $this->db->lockProfessional($professionalId);
            $existing = $this->db->one('SELECT * FROM bookings WHERE customer_id=? AND request_key=?', [$user['id'], $key]);
            if ($existing) {
                $requestedStart = Input::date(Input::text($data, 'starts_at'), new \DateTimeZone($existing['timezone']))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
                if ($existing['service_id'] !== $serviceId || $existing['professional_id'] !== $professionalId || $existing['starts_at'] !== $requestedStart) { throw new Problem('Chave da solicitação já utilizada.', 409); }
                return $existing['id'];
            }
            $service = $this->catalog->publicService($serviceId);
            $professional = $this->professional($serviceId, $professionalId);
            $startDate = Input::date(Input::text($data, 'starts_at'), new \DateTimeZone($service['timezone']));
            if ($startDate > new \DateTimeImmutable('+180 days')) { throw new Problem('Escolha uma data nos próximos 180 dias.'); }
            $start = $startDate->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            $end = $startDate->modify('+' . (int)$service['duration_minutes'] . ' minutes')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
            if (!$this->available($service, $professional, $start, $end)) { throw new Problem('Esse horário não está disponível. Escolha outro.', 409); }
            $business = $this->db->one('SELECT * FROM businesses WHERE id=?', [$service['business_id']]);
            $expiry = (int)$business['pending_minutes'] > 0 ? gmdate('Y-m-d\TH:i:s.u\Z', time() + (int)$business['pending_minutes'] * 60) : null;
            $id = $this->db->insert('bookings', ['customer_id' => $user['id'], 'business_id' => $service['business_id'], 'service_id' => $serviceId, 'professional_id' => $professionalId, 'starts_at' => $start, 'ends_at' => $end, 'status' => 'PENDING_PARTNER_APPROVAL', 'price_cents' => $service['effective_price'], 'duration_minutes' => $service['duration_minutes'], 'service_name' => $service['name'], 'professional_name' => $professional['name'], 'policy' => $service['policy'], 'timezone' => $service['timezone'], 'expires_at' => $expiry, 'request_key' => $key, 'created_at' => Database::now()]);
            $this->db->insert('conversations', ['booking_id' => $id]);
            $this->event($id, $user['id'], 'PENDING_PARTNER_APPROVAL', 'Solicitação enviada. O horário depende do aceite do parceiro.');
            return $id;
        });
    }
    public function access(array $user, string $id): array
    {
        $booking = $this->db->one('SELECT * FROM bookings WHERE id=?', [$id]);
        if (!$booking) { throw new Problem('Agendamento não encontrado.', 404); }
        if ($booking['customer_id'] !== $user['id']) { $this->catalog->membership($user, $booking['business_id']); }
        return $booking;
    }
    public function transition(array $user, string $id, string $target, string $reason): void
    {
        $this->db->transaction(function () use ($user, $id, $target, $reason): void {
            $booking = $this->access($user, $id);
            $this->db->lockProfessional($booking['professional_id']);
            // Re-read after obtaining the lock: concurrent actions must see committed state.
            $booking = $this->access($user, $id);
            $isCustomer = $booking['customer_id'] === $user['id'];
            if (!$isCustomer) { $this->catalog->membership($user, $booking['business_id']); }
            if ($isCustomer && $target !== 'CANCELED_BY_CUSTOMER') { throw new Problem('Esta ação é exclusiva do parceiro.',403); }
            if ($booking['status'] === $target) { return; }
            $allowed = $isCustomer ? ['PENDING_PARTNER_APPROVAL' => ['CANCELED_BY_CUSTOMER'], 'CONFIRMED' => ['CANCELED_BY_CUSTOMER']] : ['PENDING_PARTNER_APPROVAL' => ['CONFIRMED', 'REJECTED_BY_PARTNER'], 'CONFIRMED' => ['CANCELED_BY_PARTNER', 'IN_PROGRESS', 'NO_SHOW'], 'IN_PROGRESS' => ['COMPLETED']];
            if (!in_array($target, $allowed[$booking['status']] ?? [], true)) { throw new Problem('Esta mudança de estado não é permitida.', 409); }
            if (in_array($target, ['IN_PROGRESS','NO_SHOW'], true) && $booking['starts_at'] > Database::now()) { throw new Problem('Aguarde o horário do atendimento.'); }
            if ($target === 'CONFIRMED') {
                if ($booking['expires_at'] !== null && $booking['expires_at'] <= Database::now()) { throw new Problem('Solicitação expirada.', 409); }
                $service = $this->catalog->publicService($booking['service_id']);
                $professional = $this->professional($booking['service_id'], $booking['professional_id']);
                $end = (new \DateTimeImmutable($booking['starts_at']))->modify('+' . (int)$service['duration_minutes'] . ' minutes')->format('Y-m-d\TH:i:s.u\Z');
                if (!$this->available($service, $professional, $booking['starts_at'], $end, $id)) { throw new Problem('Conflito de agenda. Sugira outro horário pelo chat.', 409); }
                $this->db->execute('UPDATE bookings SET price_cents=?,duration_minutes=?,ends_at=?,professional_name=?,service_name=?,policy=? WHERE id=?', [$service['effective_price'], $service['duration_minutes'], $end, $professional['name'], $service['name'], $service['policy'], $id]);
            }
            $this->db->execute('UPDATE bookings SET status=? WHERE id=?', [$target, $id]);
            $this->event($id, $user['id'], $target, $reason);
        });
    }
    public function reschedule(array $user, string $id, array $data): string
    {
        return $this->db->transaction(function () use ($user, $id, $data): string {
            // Both the replacement request and retirement of the original are atomic.
            $original = $this->access($user, $id);
            $lockIds = array_unique([$original['professional_id'], Input::text($data, 'professional_id')]);
            sort($lockIds);
            foreach ($lockIds as $lockId) { $this->db->lockProfessional($lockId); }
            $original = $this->access($user, $id);
            if ($original['status'] === 'RESCHEDULED') {
                $existing = $this->db->one('SELECT id FROM bookings WHERE customer_id=? AND request_key=?', [$user['id'], Input::text($data,'request_key',16,100)]);
                if ($existing) { return $existing['id']; }
            }
            if ($original['customer_id'] !== $user['id'] || !in_array($original['status'], ['PENDING_PARTNER_APPROVAL','CONFIRMED'], true)) { throw new Problem('Reagendamento não permitido.', 403); }
            $data['service_id'] = $original['service_id'];
            $this->db->execute("UPDATE bookings SET status='RESCHEDULED' WHERE id=?", [$id]);
            $newId = $this->request($user, $data);
            if ($newId === $id) { throw new Problem('Utilize uma nova chave para reagendar.',409); }
            $this->event($id, $user['id'], 'RESCHEDULED', 'Nova solicitação: ' . $newId);
            return $newId;
        });
    }
    public function event(string $id, string $actor, string $status, string $reason): void
    {
        $this->db->insert('booking_history', ['booking_id'=>$id,'actor_id'=>$actor,'status'=>$status,'reason'=>$reason,'created_at'=>Database::now()]);
        $conversation = $this->db->one('SELECT id FROM conversations WHERE booking_id=?', [$id]);
        $body = (self::LABELS[$status] ?? $status) . '. ' . $reason;
        $this->db->insert('messages', ['conversation_id'=>$conversation['id'],'sender_id'=>null,'body'=>$body,'created_at'=>Database::now(),'is_system'=>1]);
        $booking = $this->db->one('SELECT * FROM bookings WHERE id=?', [$id]);
        $recipients = array_column($this->db->all('SELECT user_id FROM memberships WHERE business_id=?', [$booking['business_id']]), 'user_id');
        $recipients[] = $booking['customer_id'];
        foreach (array_unique($recipients) as $userId) {
            $this->db->insert('notifications', ['user_id'=>$userId,'body'=>$booking['service_name'] . ': ' . $body,'link'=>'/agendamentos','created_at'=>Database::now()]);
        }
    }
    public function expire(): void
    {
        $rows = $this->db->all("SELECT * FROM bookings WHERE status='PENDING_PARTNER_APPROVAL' AND expires_at IS NOT NULL AND expires_at<=?", [Database::now()]);
        foreach ($rows as $row) {
            $this->db->transaction(function () use ($row): void {
                $this->db->lockProfessional($row['professional_id']);
                $current = $this->db->one('SELECT * FROM bookings WHERE id=?', [$row['id']]);
                if ($current['status'] !== 'PENDING_PARTNER_APPROVAL') { return; }
                $this->db->execute("UPDATE bookings SET status='EXPIRED' WHERE id=?", [$row['id']]);
                $this->event($row['id'], $row['customer_id'], 'EXPIRED', 'Prazo de resposta do estabelecimento encerrado.');
            });
        }
    }
}
