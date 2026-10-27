<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Community;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Modules\Bookings\BookingService;
use AgendaShops\Modules\Catalog\CatalogService;
use AgendaShops\Domain\{Input, Problem};

final class CommunityService
{
    public function __construct(private Database $db, private CatalogService $catalog, private BookingService $bookings) {}

    public function favorite(array $user, string $id): void
    {
        if (!$this->db->one("SELECT id FROM businesses WHERE id=? AND status='APPROVED'", [$id])) { throw new Problem('Estabelecimento indisponível.', 404); }
        $this->db->transaction(function () use ($user, $id): void {
            if ($this->db->one('SELECT user_id FROM favorites WHERE user_id=? AND business_id=?', [$user['id'], $id])) {
                $this->db->execute('DELETE FROM favorites WHERE user_id=? AND business_id=?', [$user['id'], $id]);
            } else { $this->db->execute('INSERT INTO favorites(user_id,business_id) VALUES(?,?)', [$user['id'], $id]); }
        });
    }
    public function review(array $user, array $data): void
    {
        $id = Input::text($data, 'booking_id');
        $booking = $this->bookings->access($user, $id);
        if ($booking['customer_id'] !== $user['id'] || $booking['status'] !== 'COMPLETED') { throw new Problem('Avalie somente seus atendimentos concluídos.', 403); }
        if ($this->db->one('SELECT id FROM reviews WHERE booking_id=?', [$id])) { throw new Problem('Este atendimento já foi avaliado.'); }
        $this->db->insert('reviews', ['booking_id'=>$id,'customer_id'=>$user['id'],'business_id'=>$booking['business_id'],'rating'=>Input::integer($data, 'rating', 1, 5),'body'=>Input::text($data, 'body', 3, 2000),'created_at'=>Database::now()]);
    }
    public function conversation(array $user, string $id): array
    {
        $conversation = $this->db->one('SELECT * FROM conversations WHERE id=?', [$id]);
        if (!$conversation) { throw new Problem('Conversa não encontrada.', 404); }
        $this->bookings->access($user, $conversation['booking_id']);
        return $conversation;
    }
    public function message(array $user, array $data): void
    {
        $id = Input::text($data, 'conversation_id');
        $conversation = $this->conversation($user, $id);
        if ((int)$conversation['blocked'] === 1) { throw new Problem('Esta conversa foi bloqueada pela administração.', 403); }
        $body = Input::text($data, 'body', 1, 3000);
        $this->db->insert('messages', ['conversation_id'=>$id,'sender_id'=>$user['id'],'body'=>$body,'created_at'=>Database::now()]);
    }
    public function read(array $user, string $id): array
    {
        $this->conversation($user, $id);
        $messages = $this->db->all('SELECT m.*,u.name AS sender_name FROM messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=? ORDER BY m.created_at,m.id', [$id]);
        $readAt = $messages ? $messages[count($messages)-1]['created_at'] : '';
        $this->db->execute('INSERT INTO conversation_reads(conversation_id,user_id,read_at) VALUES(?,?,?) ON CONFLICT(conversation_id,user_id) DO UPDATE SET read_at=excluded.read_at', [$id,$user['id'],$readAt]);
        return $messages;
    }
    public function conversations(array $user): array
    {
        $rows = $this->db->all('SELECT c.*,b.service_name,b.customer_id,b.business_id,bs.name AS business_name FROM conversations c JOIN bookings b ON b.id=c.booking_id JOIN businesses bs ON bs.id=b.business_id WHERE b.customer_id=? OR b.business_id IN(SELECT business_id FROM memberships WHERE user_id=?) ORDER BY b.created_at DESC', [$user['id'],$user['id']]);
        foreach ($rows as &$row) {
            $read = $this->db->one('SELECT read_at FROM conversation_reads WHERE conversation_id=? AND user_id=?', [$row['id'],$user['id']]);
            $row['unread'] = (int)$this->db->one('SELECT COUNT(*) AS total FROM messages WHERE conversation_id=? AND (sender_id IS NULL OR sender_id<>?) AND created_at>?', [$row['id'],$user['id'],$read['read_at'] ?? ''])['total'];
        }
        return $rows;
    }
    public function report(array $user, array $data): void
    {
        $type = Input::text($data, 'target_type');
        $id = Input::text($data, 'target_id');
        if (!in_array($type, ['business','review','conversation'], true)) { throw new Problem('Tipo de denúncia inválido.'); }
        if ($type === 'conversation') { $this->conversation($user, $id); }
        $table = ['business'=>'businesses','review'=>'reviews','conversation'=>'conversations'][$type];
        if (!$this->db->one('SELECT id FROM ' . $table . ' WHERE id=?', [$id])) { throw new Problem('Conteúdo não encontrado.', 404); }
        $this->db->insert('reports', ['user_id'=>$user['id'],'target_type'=>$type,'target_id'=>$id,'reason'=>Input::text($data,'reason',10,2000),'created_at'=>Database::now()]);
    }
    public function moderate(array $user, array $data): void
    {
        $this->catalog->admin($user);
        $type = Input::text($data, 'type');
        $id = Input::text($data, 'id');
        $value = Input::text($data, 'value');
        $this->db->transaction(function () use ($user,$type,$id,$value): void {
            if ($type === 'business' && in_array($value, ['APPROVED','REJECTED','SUSPENDED'], true)) {
                if ($value === 'APPROVED' && !$this->db->one('SELECT b.id FROM businesses b JOIN users u ON u.id=b.owner_id WHERE b.id=? AND u.active=1',[$id])) { throw new Problem('O proprietário precisa ter uma conta ativa para aprovação.'); }
                $this->db->execute('UPDATE businesses SET status=? WHERE id=?', [$value,$id]);
            } elseif ($type === 'conversation' && in_array($value,['0','1'],true)) {
                $this->db->execute('UPDATE conversations SET blocked=? WHERE id=?', [(int)$value,$id]);
            } elseif ($type === 'review' && in_array($value,['0','1'],true)) {
                $this->db->execute('UPDATE reviews SET visible=? WHERE id=?', [(int)$value,$id]);
            } elseif ($type === 'report' && $value === 'RESOLVED') {
                $this->db->execute("UPDATE reports SET status='RESOLVED' WHERE id=?", [$id]);
            } else { throw new Problem('Ação de moderação inválida.'); }
            $this->catalog->audit($user, 'MODERATE_' . strtoupper($type) . '_' . $value, $id);
        });
    }
}
