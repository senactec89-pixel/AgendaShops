<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Catalog;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Domain\{Input, Problem};

final class CatalogService
{
    public function __construct(public readonly Database $db) {}

    public function membership(array $user, string $businessId, bool $ownerOnly = false): array
    {
        $member = $this->db->one('SELECT * FROM memberships WHERE user_id = ? AND business_id = ?', [$user['id'], $businessId]);
        if (!$member || ($ownerOnly && $member['role'] !== 'PARTNER_OWNER')) { throw new Problem('Você não tem permissão neste estabelecimento.', 403); }
        return $member;
    }
    public function admin(array $user): void
    {
        if ($user['role'] !== 'ADMIN') { throw new Problem('Acesso restrito à administração.', 403); }
    }
    public function audit(array $user, string $action, string $id): void
    {
        $this->db->insert('audit_events', ['actor_id' => $user['id'], 'action' => $action, 'target_id' => $id, 'created_at' => Database::now()]);
    }
    public function categories(): array
    {
        return $this->db->all('SELECT c.* FROM categories c WHERE c.active=1 AND (c.parent_id IS NULL OR EXISTS(SELECT 1 FROM categories parent WHERE parent.id=c.parent_id AND parent.active=1)) ORDER BY c.sort_order,c.name');
    }
    public function createBusiness(array $user, array $data): string
    {
        $name = Input::text($data, 'name', 2, 100);
        $timezone = Input::text($data, 'timezone');
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) { throw new Problem('Fuso horário inválido.'); }
        $data = ['owner_id' => $user['id'], 'name' => $name, 'slug' => strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $name)), '-')) . '-' . substr(Database::id(), 0, 6), 'description' => Input::text($data, 'description', 10, 3000), 'address' => Input::text($data, 'address', 5), 'city' => Input::text($data, 'city', 2, 100), 'phone' => Input::phone($data), 'timezone' => $timezone, 'policy' => Input::text($data, 'policy', 10, 2000)];
        return $this->db->transaction(function () use ($user, $data): string {
            $id = $this->db->insert('businesses', $data);
            $this->db->insert('memberships', ['business_id' => $id, 'user_id' => $user['id'], 'role' => 'PARTNER_OWNER']);
            $this->audit($user, 'BUSINESS_CREATED', $id);
            return $id;
        });
    }
    public function hours(array $data): array
    {
        $opens = Input::time($data, 'opens');
        $closes = Input::time($data, 'closes');
        $weekdays = $data['weekdays'] ?? [];
        if (!is_array($weekdays) || !$weekdays || array_diff($weekdays, ['1','2','3','4','5','6','7']) || $opens >= $closes) { throw new Problem('Confira os dias e o horário de funcionamento.'); }
        return [$opens, $closes, implode(',', array_unique($weekdays))];
    }
    public function updateBusiness(array $user, array $data): void
    {
        $id = Input::text($data, 'business_id');
        $this->membership($user, $id, true);
        [$opens, $closes, $days] = $this->hours($data);
        $latitude = trim((string)($data['latitude'] ?? ''));
        $longitude = trim((string)($data['longitude'] ?? ''));
        if (($latitude !== '' || $longitude !== '') && (!is_numeric($latitude) || !is_numeric($longitude) || abs((float)$latitude) > 90 || abs((float)$longitude) > 180)) { throw new Problem('Coordenadas inválidas.'); }
        $this->db->execute('UPDATE businesses SET name=?, description=?, address=?, city=?, phone=?, policy=?, opens=?, closes=?, weekdays=?, pending_minutes=?, latitude=?, longitude=? WHERE id=?', [Input::text($data, 'name', 2, 100), Input::text($data, 'description', 10, 3000), Input::text($data, 'address', 5), Input::text($data, 'city', 2, 100), Input::phone($data), Input::text($data, 'policy', 10, 2000), $opens, $closes, $days, Input::integer($data, 'pending_minutes', 0, 10080), $latitude ?: null, $longitude ?: null, $id]);
    }
    public function saveProfessional(array $user, array $data): string
    {
        $businessId = Input::text($data, 'business_id');
        $this->membership($user, $businessId, true);
        [$opens, $closes, $days] = $this->hours($data);
        $name = Input::text($data, 'name', 2, 100);
        $id = trim((string)($data['professional_id'] ?? ''));
        if ($id !== '') {
            if (!$this->db->one('SELECT id FROM professionals WHERE id=? AND business_id=?', [$id, $businessId])) { throw new Problem('Profissional não encontrado.', 404); }
            $this->db->execute('UPDATE professionals SET name=?, opens=?, closes=?, weekdays=?, active=? WHERE id=?', [$name, $opens, $closes, $days, isset($data['active']) ? 1 : 0, $id]);
            return $id;
        }
        return $this->db->insert('professionals', ['business_id' => $businessId, 'name' => $name, 'opens' => $opens, 'closes' => $closes, 'weekdays' => $days, 'active'=>isset($data['active']) ? 1 : 0]);
    }
    public function saveService(array $user, array $data): string
    {
        $businessId = Input::text($data, 'business_id');
        $this->membership($user, $businessId, true);
        $category = Input::text($data, 'category_id');
        if (!$this->db->one('SELECT id FROM categories WHERE id = ? AND active = 1', [$category])) { throw new Problem('Categoria indisponível.'); }
        $professionals = $data['professionals'] ?? [];
        if (!is_array($professionals) || !$professionals) { throw new Problem('Selecione pelo menos um profissional.'); }
        foreach ($professionals as $professional) {
            if (!is_string($professional) || !$this->db->one('SELECT id FROM professionals WHERE id = ? AND business_id = ? AND active = 1', [$professional, $businessId])) { throw new Problem('Profissional inválido.'); }
        }
        $name = Input::text($data, 'name', 2, 100);
        $description = Input::text($data, 'description', 5, 3000);
        $duration = Input::integer($data, 'duration_minutes', 5, 720);
        $price = Input::text($data, 'price');
        if (!preg_match('/^\d{1,6}(?:[,.]\d{1,2})?$/D', $price)) { throw new Problem('Preço inválido. Exemplo: 59,90.'); }
        $parts = explode('.', str_replace(',', '.', $price));
        $cents = (int)$parts[0] * 100 + (int)str_pad($parts[1] ?? '', 2, '0');
        $id = trim((string)($data['service_id'] ?? ''));
        return $this->db->transaction(function () use ($data, $id, $businessId, $category, $name, $description, $duration, $cents, $professionals): string {
            if ($id !== '') {
                if (!$this->db->one('SELECT id FROM services WHERE id=? AND business_id=?', [$id, $businessId])) { throw new Problem('Serviço não encontrado.', 404); }
                $this->db->execute('UPDATE services SET category_id=?,name=?,description=?,duration_minutes=?,price_cents=?,active=? WHERE id=?', [$category, $name, $description, $duration, $cents, isset($data['active']) ? 1 : 0, $id]);
                $this->db->execute('DELETE FROM service_professionals WHERE service_id=?', [$id]);
            } else {
                $id = $this->db->insert('services', ['business_id' => $businessId, 'category_id' => $category, 'name' => $name, 'description' => $description, 'duration_minutes' => $duration, 'price_cents' => $cents, 'active'=>isset($data['active']) ? 1 : 0]);
            }
            foreach (array_unique($professionals) as $professional) { $this->db->execute('INSERT INTO service_professionals(service_id,professional_id) VALUES(?,?)', [$id, $professional]); }
            return $id;
        });
    }
    public function publicService(string $id): array
    {
        $row = $this->db->one("SELECT s.*, b.name AS business_name,b.slug AS business_slug,b.city,b.address,b.timezone,b.policy,b.latitude,b.longitude,c.name AS category_name FROM services s JOIN businesses b ON b.id=s.business_id JOIN categories c ON c.id=s.category_id WHERE s.id=? AND s.active=1 AND b.status='APPROVED' AND c.active=1 AND (c.parent_id IS NULL OR EXISTS(SELECT 1 FROM categories parent WHERE parent.id=c.parent_id AND parent.active=1))", [$id]);
        if (!$row) { throw new Problem('Serviço não encontrado.', 404); }
        $rating = $this->db->one('SELECT AVG(rating) AS average,COUNT(*) AS total FROM reviews WHERE business_id=? AND visible=1',[$row['business_id']]);
        $row['rating'] = (float)($rating['average'] ?? 0);
        $row['review_count'] = (int)$rating['total'];
        $row['professionals'] = $this->db->all('SELECT p.id,p.name FROM professionals p JOIN service_professionals sp ON sp.professional_id=p.id WHERE sp.service_id=? AND p.active=1 ORDER BY p.name',[$id]);
        return $this->withPrice($row);
    }
    public function distance(array $service, array $coordinates): ?float
    {
        if (!is_numeric($coordinates['lat'] ?? null) || !is_numeric($coordinates['lng'] ?? null) || $service['latitude'] === null || $service['longitude'] === null) { return null; }
        $lat1 = deg2rad((float)$coordinates['lat']); $lat2 = deg2rad((float)$service['latitude']);
        $a = sin(($lat2-$lat1)/2)**2 + cos($lat1)*cos($lat2)*sin(deg2rad((float)$service['longitude']-(float)$coordinates['lng'])/2)**2;
        return round(6371*2*asin(sqrt(min(1,max(0,$a)))),1);
    }
    public function withPrice(array $service): array
    {
        $promo = $this->db->one('SELECT percent FROM promotions WHERE service_id=? AND starts_at<=? AND ends_at>?', [$service['id'], Database::now(), Database::now()]);
        $service['discount_percent'] = (int)($promo['percent'] ?? 0);
        $service['effective_price'] = intdiv((int)$service['price_cents'] * (100 - $service['discount_percent']) + 50, 100);
        return $service;
    }
    public function search(array $filters): array
    {
        $rows = $this->db->all("SELECT s.*, b.name AS business_name,b.slug AS business_slug,b.city,b.address,b.timezone,b.latitude,b.longitude,c.name AS category_name, COALESCE((SELECT AVG(rating) FROM reviews r WHERE r.business_id=b.id AND r.visible=1),0) AS rating, (SELECT COUNT(*) FROM reviews r WHERE r.business_id=b.id AND r.visible=1) AS review_count FROM services s JOIN businesses b ON b.id=s.business_id JOIN categories c ON c.id=s.category_id WHERE s.active=1 AND b.status='APPROVED' AND c.active=1 AND (c.parent_id IS NULL OR EXISTS(SELECT 1 FROM categories parent WHERE parent.id=c.parent_id AND parent.active=1)) ORDER BY b.name,s.name LIMIT 1000");
        $result = [];
        foreach ($rows as $row) {
            $row = $this->withPrice($row);
            $query = is_string($filters['q'] ?? null) ? mb_strtolower(trim($filters['q'])) : '';
            $city = is_string($filters['city'] ?? null) ? mb_strtolower(trim($filters['city'])) : '';
            if ($query !== '' && !str_contains(mb_strtolower($row['name'] . ' ' . $row['business_name'] . ' ' . $row['description']), $query)) { continue; }
            if ($city !== '' && !str_contains(mb_strtolower($row['city'] . ' ' . $row['address']), $city)) { continue; }
            if (!empty($filters['category']) && $row['category_id'] !== $filters['category']) { continue; }
            if (isset($filters['max_price']) && $filters['max_price'] !== '' && $row['effective_price'] > (float)$filters['max_price'] * 100) { continue; }
            if (isset($filters['min_price']) && $filters['min_price'] !== '' && $row['effective_price'] < (float)$filters['min_price'] * 100) { continue; }
            if (!empty($filters['rating']) && (float)$row['rating'] < (float)$filters['rating']) { continue; }
            $row['distance'] = $this->distance($row,$filters);
            $result[] = $row;
        }
        if (isset($filters['lat'])) { usort($result, fn($a, $b) => ($a['distance'] ?? INF) <=> ($b['distance'] ?? INF)); }
        return $result;
    }
    public function promotion(array $user, array $data): void
    {
        $id = Input::text($data, 'service_id');
        $service = $this->db->one('SELECT * FROM services WHERE id=?', [$id]);
        if (!$service) { throw new Problem('Serviço não encontrado.', 404); }
        $this->membership($user, $service['business_id'], true);
        if (($data['remove'] ?? '') === '1') { $this->db->execute('DELETE FROM promotions WHERE service_id=?', [$id]); return; }
        $business = $this->db->one('SELECT timezone FROM businesses WHERE id=?', [$service['business_id']]);
        $zone = new \DateTimeZone($business['timezone']);
        $start = Input::date(Input::text($data, 'starts_at'), $zone)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        $end = Input::date(Input::text($data, 'ends_at'), $zone)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.u\Z');
        if ($start >= $end) { throw new Problem('A promoção deve terminar depois do início.'); }
        $percent = Input::integer($data, 'percent', 1, 90);
        $this->db->transaction(function () use ($id, $start, $end, $percent): void {
            $this->db->execute('DELETE FROM promotions WHERE service_id=?', [$id]);
            $this->db->insert('promotions', ['service_id' => $id, 'starts_at' => $start, 'ends_at' => $end, 'percent' => $percent]);
        });
    }
}
