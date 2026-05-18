<?php
// FlightDeck OS / SkyCrew - Stratos Dispatch Module Routes
// Adds dispatch-specific endpoints under /api/stratos/dispatch.

if (!function_exists('fdos_sd_base_path')) {
    function fdos_sd_base_path() { return dirname(__DIR__); }
}
if (!function_exists('fdos_sd_env')) {
    function fdos_sd_env() {
        static $env = null; if ($env !== null) return $env; $env=[]; $file=fdos_sd_base_path().'/.env';
        if (file_exists($file)) { foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) { $line=trim($line); if ($line==='' || $line[0]==='#' || strpos($line,'=')===false) continue; [$k,$v]=explode('=',$line,2); $env[trim($k)] = trim(trim($v), "\"'"); } }
        return $env;
    }
}
if (!function_exists('fdos_sd_pdo')) {
    function fdos_sd_pdo() {
        static $pdo=false; if ($pdo!==false) return $pdo; $env=fdos_sd_env();
        try { $db=$env['DB_DATABASE']??null; if (!$db) return $pdo=null; $host=$env['DB_HOST']??'localhost'; $port=$env['DB_PORT']??'3306'; $user=$env['DB_USERNAME']??''; $pass=$env['DB_PASSWORD']??''; $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4",$user,$pass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]); return $pdo; } catch (Throwable $e) { return $pdo=null; }
    }
}
if (!function_exists('fdos_sd_json')) {
    function fdos_sd_json($data, $code=200) { if (!headers_sent()) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); header('Access-Control-Allow-Origin: *'); header('Access-Control-Allow-Methods: GET, POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Stratos-Token'); } echo json_encode($data, JSON_UNESCAPED_SLASHES); exit; }
}
if (!function_exists('fdos_sd_table_exists')) {
    function fdos_sd_table_exists($t) { $pdo=fdos_sd_pdo(); if(!$pdo)return false; try{$s=$pdo->prepare('SHOW TABLES LIKE ?');$s->execute([$t]); return (bool)$s->fetchColumn();}catch(Throwable $e){return false;} }
}
if (!function_exists('fdos_sd_columns')) {
    function fdos_sd_columns($t) { static $c=[]; if(isset($c[$t]))return $c[$t]; if(!fdos_sd_table_exists($t))return $c[$t]=[]; try{$rows=fdos_sd_pdo()->query('SHOW COLUMNS FROM `'.$t.'`')->fetchAll(); return $c[$t]=array_map(fn($r)=>$r['Field'],$rows);}catch(Throwable $e){return $c[$t]=[];} }
}
if (!function_exists('fdos_sd_has_col')) {
    function fdos_sd_has_col($t,$c) { return in_array($c, fdos_sd_columns($t), true); }
}
if (!function_exists('fdos_sd_input')) {
    function fdos_sd_input() { $raw=file_get_contents('php://input'); $json=json_decode($raw,true); if(!is_array($json))$json=[]; return array_merge($_GET?:[], $_POST?:[], $json); }
}
if (!function_exists('fdos_sd_headers')) {
    function fdos_sd_headers() { return function_exists('getallheaders') ? getallheaders() : []; }
}
if (!function_exists('fdos_sd_token')) {
    function fdos_sd_token() {
        $h=fdos_sd_headers(); $auth=$h['Authorization']??$h['authorization']??($_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'');
        if(preg_match('/Bearer\s+(.+)/i',$auth,$m)) return trim($m[1]);
        foreach(['X-API-Key','x-api-key','X-Stratos-Token','x-stratos-token'] as $k){ if(!empty($h[$k])) return trim($h[$k]); }
        $in=fdos_sd_input(); return $in['api_key']??$in['token']??$in['access_token']??'';
    }
}
if (!function_exists('fdos_sd_auth')) {
    function fdos_sd_auth() {
        $token=fdos_sd_token(); if(!$token || !fdos_sd_table_exists('users')) fdos_sd_json(['success'=>false,'error'=>'Invalid Token'],401);
        $cols=array_values(array_intersect(['api_key','stratos_api_token','pilot_api_token','acars_api_token','remember_token'], fdos_sd_columns('users')));
        if(!$cols) fdos_sd_json(['success'=>false,'error'=>'Invalid Token'],401);
        $where=implode(' OR ',array_map(fn($c)=>'`'.$c.'`=?',$cols));
        try{$s=fdos_sd_pdo()->prepare('SELECT * FROM users WHERE '.$where.' LIMIT 1');$s->execute(array_fill(0,count($cols),$token));$u=$s->fetch(); if(!$u) fdos_sd_json(['success'=>false,'error'=>'Invalid Token'],401); return $u;}catch(Throwable $e){fdos_sd_json(['success'=>false,'error'=>'Invalid Token'],401);}    }
}
if (!function_exists('fdos_sd_row')) {
    function fdos_sd_row($table,$id) { if(!$id||!fdos_sd_table_exists($table)||!fdos_sd_has_col($table,'id')) return null; try{$s=fdos_sd_pdo()->prepare('SELECT * FROM `'.$table.'` WHERE id=? LIMIT 1');$s->execute([$id]); return $s->fetch()?:null;}catch(Throwable $e){return null;} }
}
if (!function_exists('fdos_sd_find_booking')) {
    function fdos_sd_find_booking($id,$user) {
        if(!$id || !fdos_sd_table_exists('bookings')) return null; $cols=fdos_sd_columns('bookings');
        try{$s=fdos_sd_pdo()->prepare('SELECT * FROM bookings WHERE id=? LIMIT 1');$s->execute([$id]);$b=$s->fetch(); if(!$b)return null;}catch(Throwable $e){return null;}
        $uid=(int)($user['id']??0); foreach(['pilot_id','user_id','assigned_pilot_id'] as $c){ if(in_array($c,$cols,true) && !in_array((int)($b[$c]??0),[$uid,0],true)) return null; }
        return $b;
    }
}
if (!function_exists('fdos_sd_manifest')) {
    function fdos_sd_manifest($booking) {
        $isCargo = !empty($booking['cargo_contract_id']);
        $loads = [];
        if ($isCargo) {
            $cargoTypes = ['Pallet of dry food','Pallet of medical equipment','Aircraft parts crate','Mail containers','Humanitarian aid pallet','High value electronics','Maintenance tooling','Fresh produce pallet'];
            for ($i=1; $i<=8; $i++) { $loads[]=['type'=>$cargoTypes[($i-1)%count($cargoTypes)], 'weight_lbs'=>rand(650,2200), 'position'=>'C'.ceil($i/2).($i%2?'L':'R')]; }
        } else {
            $count = (int)($booking['passenger_count']??0); if($count<=0)$count=rand(80,180);
            $loads[]=['type'=>'Passengers','count'=>$count,'weight_lbs'=>$count*190,'position'=>'Cabin'];
            $loads[]=['type'=>'Checked baggage','count'=>$count,'weight_lbs'=>$count*35,'position'=>'Hold'];
        }
        return $loads;
    }
}
if (!function_exists('fdos_sd_briefing_payload')) {
    function fdos_sd_briefing_payload($booking,$user) {
        $aircraft = !empty($booking['aircraft_id']) ? fdos_sd_row('aircraft',$booking['aircraft_id']) : null;
        $route = !empty($booking['route_id']) ? fdos_sd_row('routes',$booking['route_id']) : null;
        $dep = $booking['departure_icao'] ?? $booking['origin_icao'] ?? $route['departure_icao'] ?? $route['origin_icao'] ?? '';
        $arr = $booking['arrival_icao'] ?? $booking['destination_icao'] ?? $route['arrival_icao'] ?? $route['destination_icao'] ?? '';
        $isCargo = !empty($booking['cargo_contract_id']) || (($aircraft['aircraft_role']??'')==='cargo');
        $manifest = fdos_sd_manifest($booking);
        $flight = ['id'=>(int)$booking['id'],'bid_id'=>(int)$booking['id'],'number'=>$booking['flight_number']??('BK'.$booking['id']),'flight_number'=>$booking['flight_number']??('BK'.$booking['id']),'departure_airport'=>$dep,'arrival_airport'=>$arr,'type'=>$isCargo?'C':'P','aircraft'=>$booking['aircraft_id']??null,'aircraft_details'=>$aircraft?['id'=>$aircraft['id']??null,'code'=>$aircraft['icao']??$aircraft['icao_code']??$aircraft['type']??'','name'=>$aircraft['name']??$aircraft['model']??$aircraft['type']??'','registration'=>$aircraft['registration']??'','maximum_passengers'=>(int)($aircraft['passenger_capacity']??0),'maximum_cargo'=>(int)($aircraft['cargo_capacity_lbs']??0)]:null];
        return ['ok'=>true,'flight'=>$flight,'booking'=>$booking,'aircraft'=>$aircraft,'route'=>$route,'manifest'=>$isCargo?[]:$manifest,'cargo_manifest'=>$isCargo?$manifest:[],'warnings'=>[],'dispatch'=>['status'=>$booking['status']??'booked','release_number'=>'FDOS-'.date('Ymd').'-'.($booking['id']??0),'generated_at'=>date('Y-m-d H:i:s'),'remarks'=>$isCargo?'Cargo dispatch loaded from SkyCrew.':'Passenger dispatch loaded from SkyCrew.']];
    }
}

if (!function_exists('fdos_sd_handle')) {
    function fdos_sd_handle() {
        $path=rtrim(parse_url($_SERVER['REQUEST_URI']??'', PHP_URL_PATH),'/'); if($path==='')$path='/'; $method=strtoupper($_SERVER['REQUEST_METHOD']??'GET');
        if(strpos($path,'/api/stratos/dispatch')!==0) return;
        if($method==='OPTIONS') fdos_sd_json(null,200);
        $user=fdos_sd_auth(); $in=fdos_sd_input();
        if($path==='/api/stratos/dispatch/current') {
            $rows=[]; if(fdos_sd_table_exists('bookings')){ $uid=(int)($user['id']??0); $cols=fdos_sd_columns('bookings'); $where=[];$params=[]; foreach(['pilot_id','user_id','assigned_pilot_id'] as $c){ if(in_array($c,$cols,true)){ $where[]='(`'.$c.'`=? OR `'.$c.'`=0 OR `'.$c.'` IS NULL)'; $params[]=$uid; break; }} if(in_array('status',$cols,true))$where[]="`status` IN ('booked','active','assigned','in_progress','pending','reserved')"; try{$s=fdos_sd_pdo()->prepare('SELECT * FROM bookings'.($where?' WHERE '.implode(' AND ',$where):'').' ORDER BY id DESC LIMIT 20');$s->execute($params);$rows=$s->fetchAll();}catch(Throwable $e){} }
            $out=[]; foreach($rows as $b)$out[]=fdos_sd_briefing_payload($b,$user)['flight']; fdos_sd_json(['ok'=>true,'flights'=>$out]);
        }
        if($path==='/api/stratos/dispatch/briefing') { $id=$in['booking_id']??$in['bid_id']??$in['flight_id']??null; $b=fdos_sd_find_booking($id,$user); if(!$b) fdos_sd_json(['ok'=>false,'error'=>'Dispatch booking not found'],404); fdos_sd_json(fdos_sd_briefing_payload($b,$user)); }
        if($path==='/api/stratos/dispatch/manifest') { $id=$in['booking_id']??$in['bid_id']??$in['flight_id']??null; $b=fdos_sd_find_booking($id,$user); if(!$b) fdos_sd_json(['ok'=>false,'error'=>'Dispatch booking not found'],404); $payload=fdos_sd_briefing_payload($b,$user); fdos_sd_json(['ok'=>true,'manifest'=>$payload['manifest'],'cargo_manifest'=>$payload['cargo_manifest']]); }
        if($path==='/api/stratos/dispatch/release' && $method==='POST') { $id=$in['booking_id']??$in['bid_id']??$in['flight_id']??null; $b=fdos_sd_find_booking($id,$user); if(!$b) fdos_sd_json(['ok'=>false,'error'=>'Dispatch booking not found'],404); if(fdos_sd_table_exists('bookings')&&fdos_sd_has_col('bookings','status')){ try{$s=fdos_sd_pdo()->prepare("UPDATE bookings SET status='dispatched' WHERE id=? LIMIT 1");$s->execute([$b['id']]);$b['status']='dispatched';}catch(Throwable $e){} } fdos_sd_json(fdos_sd_briefing_payload($b,$user)); }
        fdos_sd_json(['ok'=>false,'error'=>'Route not found','path'=>$path],404);
    }
}

fdos_sd_handle();
?>
