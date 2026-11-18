<?php
declare(strict_types=1);

use KingdomConnections\JsonStore;

trait ServiceRecords
{
    public function removeAccountData(string $id): void
    {
        JsonStore::update($this->path, static function(array $records) use ($id): array {
            unset($records['_customers'][$id]);
            foreach ($records as $key=>&$row) {
                if ($key === '_customers') continue;
                if (($row['owner'] ?? '') === $id) { unset($records[$key]); continue; }
                foreach ($row['payments'] ?? [] as $pid=>$p) if ($p['customer'] === $id) unset($row['payments'][$pid]);
                $row['ratings'] = array_values(array_filter($row['ratings'], static fn($r)=>$r['recordedBy'] !== $id));
            }
            unset($row);
            return $records;
        });
    }

    public function customers(): array
    {
        return JsonStore::read($this->path)['records']['_customers'] ?? [];
    }

    public function saveCustomer(string $id, array $input): void
    {
        if (!preg_match('/^[a-zA-Z0-9_-]{1,80}$/D', $id)) throw new InvalidArgumentException('Enter a valid customer ID.');
        $name = self::text($input, 'name', 160, true);
        $email = self::text($input, 'email', 254);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Check the email address.');
        JsonStore::update($this->path, static function(array $records) use ($id, $name, $email): array {
            $records['_customers'][$id] = compact('name', 'email');
            return $records;
        });
    }

    public function serviceAction(string $action, array $input, string $actor, bool $super): void
    {
        $id = self::text($input, 'id', 80, true);
        $key = self::text($input, 'key', 80);
        JsonStore::update($this->path, static function(array $records) use ($action, $input, $actor, $super, $id, $key): array {
            if ($action === 'delete-customer' && $super) {
                unset($records['_customers'][$id]);
                foreach ($records as $businessId => &$business) {
                    if ($businessId === '_customers') continue;
                    foreach ($business['payments'] ?? [] as $pid => $payment) {
                        if ($payment['customer'] === $id) unset($business['payments'][$pid]);
                    }
                    $business['ratings'] = array_values(array_filter($business['ratings'], static fn($r) => $r['recordedBy'] !== $id));
                }
                unset($business);
                return $records;
            }
            if ($id === '_customers' || !isset($records[$id])) throw new InvalidArgumentException('Business not found.');
            $business = &$records[$id];
            if ($action === 'delete-business' && $super) {
                unset($records[$id]);
            } elseif ($action === 'claim') {
                if (!isset($records['_customers'][$actor]) || ($business['owner'] ?? '') === $actor) throw new InvalidArgumentException('An eligible customer profile is required.');
                $reference = self::text($input, 'reference', 120, true);
                foreach ($business['payments'] ?? [] as $p) {
                    if (strcasecmp($p['reference'], $reference) === 0) throw new InvalidArgumentException('This reference has already been submitted.');
                }
                $business['payments'][bin2hex(random_bytes(16))] = ['customer'=>$actor, 'reference'=>$reference, 'service'=>self::text($input, 'service', 200, true), 'verified'=>false];
            } elseif ($action === 'verify' && $super && isset($business['payments'][$key])) {
                if (($input['evidence'] ?? '') !== 'checked') throw new InvalidArgumentException('Confirm the merchant payment record first.');
                $business['payments'][$key]['verified'] = true;
                $business['payments'][$key]['verifiedBy'] = $actor;
                $business['payments'][$key]['verifiedAt'] = gmdate(DATE_ATOM);
            } elseif ($action === 'review') {
                $payment = $business['payments'][$key] ?? [];
                if (empty($payment['verified']) || $payment['customer'] !== $actor || ($business['owner'] ?? '') === $actor || in_array($key, array_column($business['ratings'], 'payment'), true)) throw new InvalidArgumentException('A verified, unused payment belonging to you is required.');
                $business['ratings'][] = self::reviewFields($input) + ['id'=>bin2hex(random_bytes(16)), 'payment'=>$key, 'source'=>'verified-payment', 'recordedBy'=>$actor, 'createdAt'=>gmdate(DATE_ATOM)];
            } elseif ($action === 'delete-payment' && $super) {
                unset($business['payments'][$key]);
                $business['ratings'] = array_values(array_filter($business['ratings'], static fn($r) => ($r['payment'] ?? '') !== $key));
            } elseif ($action === 'edit-payment' && $super && isset($business['payments'][$key])) {
                $reference = self::text($input, 'reference', 120, true);
                foreach ($business['payments'] as $pid=>$p) {
                    if ($pid !== $key && strcasecmp($p['reference'], $reference) === 0) throw new InvalidArgumentException('Reference already exists.');
                }
                $business['payments'][$key] = ['reference'=>$reference, 'service'=>self::text($input,'service',200,true), 'customer'=>$business['payments'][$key]['customer'], 'verified'=>false];
                $business['ratings'] = array_values(array_filter($business['ratings'], static fn($r) => ($r['payment'] ?? '') !== $key));
            } elseif (in_array($action, ['edit-review','delete-review'], true) && $super) {
                $pos = array_search($key, array_column($business['ratings'], 'id'), true);
                if ($pos === false) throw new InvalidArgumentException('Review missing.');
                if ($action === 'delete-review') array_splice($business['ratings'], $pos, 1);
                else $business['ratings'][$pos] = array_replace($business['ratings'][$pos], self::reviewFields($input), ['editedByAdmin'=>true]);
            } else throw new InvalidArgumentException('Action unavailable.');
            return $records;
        });
    }

    private static function reviewFields(array $input): array
    {
        $stars = filter_var($input['stars'] ?? null, FILTER_VALIDATE_INT);
        if ($stars === false || $stars < 1 || $stars > 5) throw new InvalidArgumentException('Choose 1–5 stars.');
        return ['stars'=>$stars, 'comment'=>self::text($input, 'comment', 2000, true)];
    }
}
