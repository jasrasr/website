<?php
declare(strict_types=1);
use KingdomConnections\JsonStore;

final class Identity
{
    public function __construct(private string $folder) {}
    public function accounts(): array { return JsonStore::read($this->folder.'/accounts.json')['records']; }

    public function account(string $id, string $username, string $name, string $role, string $password, bool $first = false): string
    {
        $username = strtolower(trim($username));
        if (!preg_match('/^[a-z0-9][a-z0-9_.-]{2,59}$/D',$username) || trim($name)==='' || strlen($name)>160) throw new InvalidArgumentException('Enter a name and a 3–60 character username.');
        if (!in_array($role,['super','business','customer'],true)) throw new InvalidArgumentException('Unknown role.');
        $hash = null;
        $new = $id === '';
        if ($new || $password !== '') {
            if (strlen($password)<12 || strlen($password)>72) throw new InvalidArgumentException('Use a password of 12–72 bytes.');
            $hash = password_hash($password,PASSWORD_DEFAULT);
        }
        if ($new) $id = bin2hex(random_bytes(16));
        JsonStore::update($this->folder.'/accounts.json',static function(array $rows) use ($new,$first,$id,$username,$name,$role,$hash): array {
            if (!$first && !$rows && $role !== 'super') throw new InvalidArgumentException('The administrator must initialize this directory first.');
            if ($first && $rows) throw new InvalidArgumentException('Accounts already exist.');
            if (!$new && !isset($rows[$id])) throw new InvalidArgumentException('Account missing.');
            foreach ($rows as $key=>$row) if ($key!==$id && $row['username']===$username) throw new InvalidArgumentException('Username unavailable.');
            if (!$new && $role!==$rows[$id]['role']) throw new InvalidArgumentException('Account roles are fixed.');
            $rows[$id] = ['id'=>$id,'username'=>$username,'name'=>$name,'role'=>$role,'hash'=>$hash ?? $rows[$id]['hash'],'version'=>($rows[$id]['version'] ?? 0)+1];
            return $rows;
        });
        return $id;
    }
    public function remove(string $id): void
    {
        JsonStore::update($this->folder.'/accounts.json',static function(array $rows) use ($id): array {
            if (($rows[$id]['role'] ?? 'super')==='super') throw new InvalidArgumentException('Choose a customer or business account.');
            unset($rows[$id]); return $rows;
        });
    }
    public function signIn(string $username,string $password): void
    {
        $username = strtolower(trim($username));
        $keys = [hash('sha256','user:'.$username)=>10,hash('sha256','ip:'.($_SERVER['REMOTE_ADDR'] ?? 'cli'))=>80];
        JsonStore::update($this->folder.'/attempts.json',static function(array $rows) use ($keys): array {
            $now=time(); $rows=array_filter($rows, static fn($row)=>$row['until']>$now);
            foreach ($keys as $key=>$limit) {
                $rows[$key] ??= ['until'=>$now+900,'count'=>0];
                if ($rows[$key]['count'] >= $limit) throw new InvalidArgumentException('Too many attempts. Try again later.');
                ++$rows[$key]['count'];
            }
            return $rows;
        });
        foreach ($this->accounts() as $account) {
            if ($account['username']===$username && password_verify($password,$account['hash'])) {
                session_regenerate_id(true);
                $_SESSION['identity'] = [$account['id'],$account['version']];
                $_SESSION['csrf'] = bin2hex(random_bytes(32));
                return;
            }
        }
        throw new InvalidArgumentException('Check your username and password.');
    }
    public function user(): ?array
    {
        [$id,$version] = $_SESSION['identity'] ?? ['',0];
        if ($id==='') return null;
        $account=$this->accounts()[$id] ?? null;
        if (!$account || $account['version']!==$version) return null;
        unset($account['hash']); return $account;
    }
    public function requireRole(string ...$roles): array
    {
        $user=$this->user();
        if (!$user) { header('Location: login.php',true,303); exit; }
        if (!in_array($user['role'],$roles,true)) { http_response_code(403); exit('Access denied.'); }
        return $user;
    }
    public function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
    public function validCsrf(mixed $token): bool { return is_string($token) && hash_equals($this->csrf(),$token); }
}
