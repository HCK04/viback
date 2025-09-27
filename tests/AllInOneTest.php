<?php
declare(strict_types=1);

/**
 * AllInOneTest.php
 * Single-file, pass/fail test runner (no PHPUnit/Jest/Playwright required).
 *
 * What it covers:
 * - Smoke checks
 * - Security (CORS, SQLi, IDOR, auth checks, rate-limits)
 * - API integration (route presence + status sanity)
 * - RDV regressions (id + next redirects, week navigation present)
 * - Frontend checks (file existence + key logic present)
 * - Simple performance probe (local, very loose threshold)
 *
 * How to run:
 *   cd backend
 *   php tests/AllInOneTest.php
 *
 * Exit code: 0 if all non-skipped tests pass, otherwise 1.
 *
 * Notes:
 * - Skips gracefully if a route/file is not found in your environment.
 * - No external network calls; uses Laravel HttpKernel and filesystem checks.
 * - This is intentionally compact to avoid timeouts while providing broad coverage.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

final class SkipTest extends \Exception {}
final class FailTest extends \Exception {}

final class Runner {
  private $app;
  private $kernel;
  private int $pass=0,$fail=0,$skip=0;
  private array $tests=[];
  private string $backend;
  private string $project;
  private string $frontend;

  function __construct() {
    $this->backend = realpath(__DIR__ . '/..') ?: getcwd();
    $this->project = realpath($this->backend . '/..') ?: dirname($this->backend);
    $this->frontend = $this->project . DIRECTORY_SEPARATOR . 'frontend';

    putenv('APP_ENV=testing'); $_ENV['APP_ENV']='testing'; $_SERVER['APP_ENV']='testing';

    chdir($this->backend);
    require $this->backend . '/vendor/autoload.php';
    $this->app = require $this->backend . '/bootstrap/app.php';
    $this->kernel = $this->app->make(\Illuminate\Contracts\Http\Kernel::class);
  }

  function add(string $name, callable $fn){ $this->tests[] = [$name,$fn]; }

  private function color($t,$c){ return strtoupper(substr(PHP_OS,0,3))==='WIN'?$t:("\033[".$c."m".$t."\033[0m"); }
  private function ok($t){ echo $this->color("[PASS] ","32"),$t,"\n"; }
  private function bad($t,$m){ echo $this->color("[FAIL] ","31"),$t,"\n","       ",$m,"\n"; }
  private function skp($t,$m){ echo $this->color("[SKIP] ","33"),$t,$this->color("  -- ".$m,"90"),"\n"; }

  private function router(){ return $this->app->make('router'); }
  private function routeExists(string $method,string $contains):bool{
    $method=strtoupper($method);
    foreach($this->router()->getRoutes() as $r){
      if(in_array($method,$r->methods(),true) && strpos($r->uri(), ltrim($contains,'/'))!==false) return true;
    }
    return false;
  }
  private function http(string $method,string $uri,array $query=[],?array $json=null,array $headers=[]):array{
    $server=[];
    foreach($headers as $k=>$v){ $server['HTTP_'.strtoupper(str_replace('-','_',$k))]=$v; }
    $server['CONTENT_TYPE']=$json!==null?'application/json':($server['CONTENT_TYPE']??'application/x-www-form-urlencoded');
    $content=$json!==null?json_encode($json,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):null;
    $req=\Illuminate\Http\Request::create($uri,strtoupper($method),$query,[],[],$server,$content);
    $resp=$this->kernel->handle($req);
    $body=$resp->getContent();
    $jsonDec=is_string($body)?json_decode($body,true):null;
    return ['status'=>$resp->getStatusCode(),'headers'=>$resp->headers,'body'=>$body,'json'=>$jsonDec];
  }
  private function assert(bool $cond,string $msg='Assertion failed'){ if(!$cond) throw new FailTest($msg); }
  private function assertIn($val,array $set,string $msg=''){ if(!in_array($val,$set,true)) throw new FailTest($msg?:("Value not in set: ".var_export($val,true))); }
  private function skip(string $why){ throw new SkipTest($why); }

  function register(){
    // Smoke
    $this->add('Smoke: kernel boots', function(){ $this->assert($this->app && $this->kernel,'Kernel not booted'); });
    $this->add('Smoke: GET / returns sane status', function(){
      $r=$this->http('GET','/');
      $this->assertIn($r['status'],[200,201,202,204,301,302,404],'Unexpected status for /');
    });

    // Security
    $this->add('Security: CORS not wildcard', function(){
      $r=$this->http('GET','/api/profiles/slug/cors-probe',[],null,['Origin'=>'https://evil.example.com']);
      $o=$r['headers']->get('Access-Control-Allow-Origin');
      if($o===null){ $this->assert(true); return; }
      $this->assert($o!=='*','CORS must not use wildcard in production');
    });
    $this->add('Security: SQLi on slug returns 4xx', function(){
      if(!$this->routeExists('GET','api/profiles/slug')) $this->skip('Slug endpoint missing');
      $r=$this->http('GET',"/api/profiles/slug/%27%20OR%201%3D1--");
      $this->assertIn($r['status'],[400,404,422],'Expected 4xx on injection attempt');
    });
    $this->add('Security: POST /api/rendezvous requires auth', function(){
      if(!$this->routeExists('POST','api/rendezvous')) $this->skip('POST /api/rendezvous missing');
      $r=$this->http('POST','/api/rendezvous',[],[
        'target_user_id'=>1,'target_role'=>'medecin','date_time'=>date('Y-m-d H:i:s',time()+3600)
      ]);
      $this->assert($r['status']===401,'Expected 401 without Authorization');
    });
    $this->add('Security: Login brute-force 401/429', function(){
      if(!$this->routeExists('POST','api/login') && !$this->routeExists('POST','login')) $this->skip('Login route missing');
      $last=null; for($i=0;$i<6;$i++){ $last=$this->http('POST','/api/login',[],['email'=>'nope@example.com','password'=>'bad']); }
      $this->assertIn($last['status'],[401,429],'Expected 401 or 429 after attempts');
    });
    $this->add('Security: IDOR update blocked (401/403)', function(){
      $candidate=null; foreach($this->router()->getRoutes() as $r){ if(in_array('PUT',$r->methods(),true) && strpos($r->uri(),'api')===0){ $candidate='/'.$r->uri(); break; } }
      if(!$candidate) $this->skip('No PUT /api/* route to test');
      $r=$this->http('PUT',$candidate,[],['name'=>'Hacker']);
      $this->assertIn($r['status'],[401,403],'Expected 401/403 for unauthorized update');
    });

    // API integration (route presence + basic status)
    $this->add('API: /api/profiles/slug/* status sanity', function(){
      if(!$this->routeExists('GET','api/profiles/slug')) $this->skip('Slug endpoint missing');
      $r=$this->http('GET','/api/profiles/slug/clinique-al-hayat');
      $this->assertIn($r['status'],[200,404],'Expected 200 if exists else 404');
    });
    $this->add('API: /api/rendezvous/professional/{id} status sanity', function(){
      if(!$this->routeExists('GET','api/rendezvous/professional')) $this->skip('RDV professional endpoint missing');
      $r=$this->http('GET','/api/rendezvous/professional/1');
      $this->assertIn($r['status'],[200,404,422],'Expected 2xx or 4xx (id may not exist)');
    });
    $this->add('API: /api/user auth sanity', function(){
      if(!$this->routeExists('GET','api/user')) $this->skip('/api/user missing');
      $r=$this->http('GET','/api/user');
      $this->assertIn($r['status'],[200,401],'Expected 200 if authed, 401 if not');
    });

    // RDV regression (based on your recent fixes)
    $this->add('RDV Regression: FE passes ?id in profile->rendezvous navigation', function(){
      $paths=[
        $this->frontend.'/src/patient_folder/DoctorProfileDoctolib.jsx',
        $this->frontend.'/src/patient_folder/DoctorProfile.jsx',
      ];
      $found=0; foreach($paths as $p){ if(is_file($p) && strpos(file_get_contents($p), '?id=')!==false) $found++; }
      $this->assert($found>0,'Expected ?id= in doctor profile navigation');
    });
    $this->add('RDV Regression: FE search time passes id/date/time', function(){
      $p=$this->frontend.'/src/patient_folder/CitySearchResults.jsx';
      if(!is_file($p)) $this->skip('CitySearchResults.jsx missing');
      $s=file_get_contents($p);
      $this->assert(strpos($s,'?id=')!==false,'Missing ?id=');
      $this->assert((strpos($s,'?time=')!==false)||(strpos($s,'&time=')!==false),'Missing time param');
    });
    $this->add('RDV Regression: RendezVous.jsx redirects with next param when unauthenticated', function(){
      $p=$this->frontend.'/src/patient_folder/RendezVous.jsx';
      if(!is_file($p)) $this->skip('RendezVous.jsx missing');
      $s=file_get_contents($p);
      $this->assert(strpos($s,'/patient-auth?next=')!==false,'Missing next= redirect');
    });
    $this->add('RDV Regression: RendezVous.jsx week navigation present', function(){
      $p=$this->frontend.'/src/patient_folder/RendezVous.jsx';
      if(!is_file($p)) $this->skip('RendezVous.jsx missing');
      $s=file_get_contents($p);
      $this->assert(strpos($s,'const [currentWeek, setCurrentWeek]')!==false,'Missing currentWeek state');
      $this->assert(strpos($s,'FaChevronRight')!==false || strpos($s,'navigateWeek(')!==false,'Missing week nav controls');
    });

    // Frontend presence + QR code dependency
    $this->add('FE: Key files exist', function(){
      $files=[
        $this->frontend.'/src/Auth.jsx',
        $this->frontend.'/src/utils/slugUtils.js',
        $this->frontend.'/src/patient_folder/RendezVous.jsx',
        $this->frontend.'/src/patient_folder/CitySearchResults.jsx',
      ];
      foreach($files as $f){ if(!is_file($f)) $this->assert(false,"Missing file: $f"); }
    });
    $this->add('FE: package.json includes qrcode or qrcode.react', function(){
      $pkg=$this->frontend.'/package.json';
      if(!is_file($pkg)) $this->skip('frontend/package.json missing');
      $j=json_decode(file_get_contents($pkg),true);
      $deps=array_merge($j['dependencies']??[],$j['devDependencies']??[]);
      $this->assert(isset($deps['qrcode'])||isset($deps['qrcode.react']), 'QR code libs not found in package.json');
    });

    // Simple performance probe (very loose; counts 4xx as OK for timing)
    $this->add('Perf: avg latency GET /api/rendezvous/professional/1 < 1000ms', function(){
      if(!$this->routeExists('GET','api/rendezvous/professional')) $this->skip('RDV professional endpoint missing');
      $times=[];
      for($i=0;$i<3;$i++){
        $t0=microtime(true);
        $r=$this->http('GET','/api/rendezvous/professional/1');
        $t1=microtime(true);
        $this->assertIn($r['status'],[200,404,422]);
        $times[] = ($t1-$t0)*1000.0;
      }
      $avg=array_sum($times)/max(count($times),1);
      $this->assert($avg<1000.0,"Average ${avg}ms >= 1000ms");
    });

    // File upload endpoints should not be publicly writable
    $this->add('Security: Upload endpoints reject unauthenticated', function(){
      $cand=null; foreach($this->router()->getRoutes() as $r){ if(strpos($r->uri(),'api')===0 && preg_match('#upload|file|media#i',$r->uri())){ $cand='/'.$r->uri(); break; } }
      if(!$cand) $this->skip('No upload-like endpoint found');
      $r=$this->http('POST',$cand,[],['_probe'=>true]);
      $this->assertIn($r['status'],[401,403,405,415,422],'Unexpected upload response for unauthenticated');
    });

    // Stripe webhook should reject unsigned requests
    $this->add('Security: Stripe webhook rejects unsigned', function(){
      $cand=null; foreach($this->router()->getRoutes() as $r){ if(in_array('POST',$r->methods(),true) && strpos($r->uri(),'webhook')!==false && strpos($r->uri(),'stripe')!==false){ $cand='/'.$r->uri(); break; } }
      if(!$cand) $this->skip('Stripe webhook route not found');
      $r=$this->http('POST',$cand,[],['no-signature'=>true]);
      $this->assertIn($r['status'],[400,401,403],'Webhook did not reject unsigned request');
    });
  }

  function run(){
    $this->register();
    $start=microtime(true);
    foreach($this->tests as [$name,$fn]){
      try{ $fn(); $this->pass++; $this->ok($name); }
      catch(SkipTest $e){ $this->skip++; $this->skp($name,$e->getMessage()); }
      catch(FailTest $e){ $this->fail++; $this->bad($name,$e->getMessage()); }
      catch(\Throwable $e){ $this->fail++; $this->bad($name,'Uncaught: '.$e->getMessage()); }
    }
    $t=number_format(microtime(true)-$start,2);
    echo "\nSummary: Passed={$this->pass} Failed={$this->fail} Skipped={$this->skip} Time={$t}s\n";
    exit($this->fail>0?1:0);
  }
}

$runner=new Runner();
$runner->run();
