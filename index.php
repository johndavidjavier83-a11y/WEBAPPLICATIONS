<?php
require 'config.php';
$p = $_GET['p'] ?? 'dashboard'; $err = ''; $u = null;
if (!empty($_SESSION['uid'])) $u = q('SELECT * FROM users WHERE id=?', [$_SESSION['uid']])->fetch();
$admin = $u && $u['role'] === 'admin';
$ini = $u ? strtoupper(substr($u['full_name'],0,1).(strpos($u['full_name'],' ')!==false?substr(strrchr($u['full_name'],' '),1,1):'')) : '?';
function redir($to){ header("Location: index.php?p=$to"); exit; }
function badge($s){ $c=['Pending'=>'warn','In Progress'=>'info','Urgent'=>'danger','Resolved'=>'ok']; return '<span class="badge '.($c[$s]??'info').'"><i></i>'.e($s).'</span>'; }
function prio($s){ $c=['Low'=>'ok','Medium'=>'warn','High'=>'orange','Urgent'=>'danger']; return '<span class="pill '.($c[$s]??'').'">'.e($s).'</span>'; }
$puroks = ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6'];
function purokSelect($name,$sel=''){ global $puroks; $o='<option value="">Select your purok</option>'; foreach($puroks as $x) $o.='<option'.($x===$sel?' selected':'').'>'.$x.'</option>'; return "<select name='$name' required>$o</select>"; }

if ($p === 'logout') { session_destroy(); redir('login'); }

/* ---------- POST handlers ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['a'] ?? ''; $d = $_POST;
  if ($a === 'login') {
    $r = q('SELECT * FROM users WHERE email=?', [trim($d['email'])])->fetch();
    if ($r && password_verify($d['password'], $r['password'])) { $_SESSION['uid'] = $r['id']; redir('dashboard'); }
    $err = 'Email or password is incorrect.'; $p = 'login';
  } elseif ($a === 'register') {
    if (strlen($d['password']) < 8 || $d['password'] !== $d['confirm']) $err = 'Passwords must match and be at least 8 characters.';
    elseif (q('SELECT id FROM users WHERE email=?', [$d['email']])->fetch()) $err = 'That email is already registered. Sign in instead.';
    else {
      $first = !q('SELECT id FROM users LIMIT 1')->fetch();
      q('INSERT INTO users(full_name,email,mobile,barangay,municipality,purok,password,role) VALUES(?,?,?,?,?,?,?,?)',
        [$d['full_name'],$d['email'],$d['mobile'],$d['barangay'],$d['municipality'],$d['purok'],password_hash($d['password'],PASSWORD_DEFAULT),$first?'admin':'resident']);
      $_SESSION['uid'] = $pdo->lastInsertId(); redir('dashboard');
    }
    $p = 'register';
  } elseif (in_array($a, ['complaint','disaster']) && ($u || $a === 'disaster')) {
    $isD = $a === 'disaster';
    $map = ['Low'=>'Low','Moderate'=>'Medium','High'=>'High','Critical'=>'Urgent'];
    $sev = $d['severity'] ?? ''; $prio = $isD ? ($map[$sev] ?? 'High') : 'Medium';
    $subject = $isD ? $d['category'].' at '.$d['purok'] : $d['subject'];
    q('INSERT INTO reports(type,user_id,category,severity,subject,description,barangay,municipality,purok,landmark,people_affected,need,contact,lat,lng,anonymous,sms_updates,priority,status,assigned_to) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
      [$a,$u['id']??null,$d['category'],$sev,$subject,$d['description']??'',$d['barangay']??'Talon',$d['municipality']??'Amadeo, Cavite',$d['purok'],$d['landmark']??'',
       $d['people_affected']??null,$d['need']??null,$d['contact']??null,$d['lat']?:null,$d['lng']?:null,!empty($d['anonymous'])?1:0,!empty($d['sms'])?1:0,
       $prio,($isD && $prio==='Urgent')?'Urgent':'Pending',$isD?'MDRRMO Amadeo':'Barangay Maintenance Team']);
    $id = $pdo->lastInsertId(); $ref = ($isD?'DIS':'CMP').'-'.date('Y').'-'.str_pad($id,4,'0',STR_PAD_LEFT);
    q('UPDATE reports SET ref_no=? WHERE id=?', [$ref,$id]);
    q('INSERT INTO report_updates(report_id,title) VALUES(?,?)', [$id,'Report submitted']);
    foreach (array_slice($_FILES['photos']['tmp_name'] ?? [], 0, 4) as $i => $tmp) {
      $ext = strtolower(pathinfo($_FILES['photos']['name'][$i], PATHINFO_EXTENSION));
      if ($tmp && in_array($ext,['jpg','jpeg','png']) && $_FILES['photos']['size'][$i] <= 10485760) {
        $fn = uniqid('p_').'.'.$ext; move_uploaded_file($tmp, "uploads/$fn"); q('INSERT INTO report_photos(report_id,file) VALUES(?,?)', [$id,$fn]);
      }
    }
    redir("done&id=$id");
  } elseif ($a === 'comment' && $u) {
    q('INSERT INTO report_updates(report_id,user_id,title,message) VALUES(?,?,?,?)', [$d['id'],$u['id'],'Resident update',$d['message']]); redir("report&id={$d['id']}");
  } elseif ($a === 'status' && $admin) {
    q('UPDATE reports SET status=? WHERE id=?', [$d['status'],$d['id']]);
    q('INSERT INTO report_updates(report_id,user_id,title) VALUES(?,?,?)', [$d['id'],$u['id'],'Status changed to '.$d['status']]); redir("report&id={$d['id']}");
  } elseif ($a === 'profile' && $u) {
    q('UPDATE users SET full_name=?,mobile=?,email=?,purok=?,postal_code=?,pref_sms=?,pref_status=?,pref_announce=? WHERE id=?',
      [$d['full_name'],$d['mobile'],$d['email'],$d['purok'],$d['postal_code'],isset($d['pref_sms']),isset($d['pref_status']),isset($d['pref_announce']),$u['id']]); redir('profile&saved=1');
  } elseif ($a === 'password' && $u) {
    if (password_verify($d['old'],$u['password']) && strlen($d['new'])>=8) { q('UPDATE users SET password=? WHERE id=?',[password_hash($d['new'],PASSWORD_DEFAULT),$u['id']]); redir('profile&saved=1'); }
    $err = 'Current password is wrong or new password is under 8 characters.'; $p = 'profile';
  }
}

$authPages = ['login','register'];
if (!$u && !in_array($p,$authPages) && $p !== 'disaster') $p = 'login';
if ($u && in_array($p,$authPages)) $p = 'dashboard';
if ($p === 'admin' && !$admin) $p = 'dashboard';

/* ---------- Layout pieces ---------- */
function authShell($inner){ ?>
<div class="auth"><aside class="hero"><div class="brand"><b class="logo">🛡</b><div><strong>BayanAlert</strong><small>Community Complaint &amp; Disaster Reporting</small></div></div>
<div class="hero-copy"><h1>Report. Respond. Recover.</h1><p>One platform for Barangay Talon and the Municipality of Amadeo to file community complaints and report disasters — fast, transparent and trackable.</p>
<ul><li>Real-time status tracking for every report</li><li>Disaster alerts, hazard map &amp; evacuation centers</li><li>Direct line to barangay officials and MDRRMO</li></ul></div><small>© 2026 Barangay Talon, Amadeo, Cavite</small></aside>
<main class="auth-form"><?= $inner ?></main></div><?php }

function shell($title,$sub,$body){ global $u,$admin,$p,$ini;
  $nav=['dashboard'=>['▦','Dashboard'],'complaint'=>['📄','Submit Complaint'],'disaster'=>['⚠','Report Disaster'],'reports'=>['🕘','My Reports'],'map'=>['📍','Hazard Map'],'announcements'=>['📢','Announcements'],'profile'=>['⚙','Profile & Settings']];
  $act = in_array($p,['report','done'])?'reports':$p; ?>
<div class="app"><aside class="side"><div class="brand"><b class="logo">🛡</b><div><strong>BayanAlert</strong><small>Talon · Amadeo, Cavite</small></div></div>
<nav><h6>MENU</h6><?php foreach($nav as $k=>$v): ?><a href="?p=<?=$k?>" class="<?=$act===$k?'on':''?>"><span><?=$v[0]?></span><?=$v[1]?></a><?php endforeach; ?>
<?php if($admin): ?><h6>ADMINISTRATION</h6><a href="?p=admin" class="<?=$p==='admin'?'on':''?>"><span>🛡</span>Admin Console</a><?php endif; ?></nav>
<div class="me"><?php if($u): ?><div class="user"><b class="av"><?=e($ini)?></b><div><strong><?=e($u['full_name'])?></strong><small><?=$admin?'Admin':'Resident'?> · Brgy. <?=e($u['barangay'])?></small></div></div><a href="?p=logout" class="out">⇦ Log out</a><?php else: ?><a href="?p=login" class="out">Sign in</a><?php endif; ?></div></aside>
<section class="main"><header class="top"><div><h2><?=e($title)?></h2><small><?=e($sub)?></small></div>
<input class="search" id="gsearch" placeholder="Search reports, announcements..."><button class="bell">🔔</button><b class="av"><?=e($ini)?></b></header>
<div class="content"><?= $body ?></div></section></div><?php }

function reportRows($rows){ foreach($rows as $r): ?>
<a class="row" href="?p=report&id=<?=$r['id']?>" data-s="<?=e(strtolower($r['subject'].' '.$r['ref_no']))?>"><div><strong><?=e($r['subject'])?></strong><small><?=e($r['ref_no'])?></small></div><span><?=e($r['category'])?></span><span><?=e($r['purok'])?></span><?=badge($r['status'])?><span><?=date('M d',strtotime($r['created_at']))?></span><em>›</em></a>
<?php endforeach; }

?><!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>BayanAlert</title><link rel="stylesheet" href="assets/style.css"></head><body>
<?php
ob_start();
switch ($p):
/* ---------- Login / Register ---------- */
case 'login': ?>
<form method="post" class="authbox"><input type="hidden" name="a" value="login"><h2>Welcome back</h2><p class="muted">Sign in to report concerns and track your requests.</p>
<?php if($err): ?><div class="err"><?=e($err)?></div><?php endif; ?>
<label>Email address<input type="email" name="email" placeholder="juan.delacruz@email.com" required></label>
<label>Password<input type="password" name="password" required></label>
<div class="between"><label class="chk"><input type="checkbox" checked> Remember me</label><a href="#" class="link">Forgot password?</a></div>
<button class="btn primary block">Sign In</button><div class="or">OR</div>
<a class="btn block" href="?p=disaster">Report an emergency without signing in</a><p class="center muted">Don't have an account? <a class="link" href="?p=register">Create one</a></p></form>
<?php authShell(ob_get_clean()); break;

case 'register': ?>
<form method="post" class="authbox wide"><input type="hidden" name="a" value="register"><h2>Create your account</h2><p class="muted">Register as a resident of Amadeo, Cavite to file reports.</p>
<?php if($err): ?><div class="err"><?=e($err)?></div><?php endif; ?>
<label>Full name<input name="full_name" placeholder="Juan Dela Cruz" required></label>
<div class="grid2"><label>Email address<input type="email" name="email" required></label><label>Mobile number<input name="mobile" placeholder="09XX XXX XXXX"></label>
<label>Barangay<select name="barangay"><option>Talon</option></select></label><label>Municipality<select name="municipality"><option>Amadeo, Cavite</option></select></label></div>
<label>Purok / Sitio<?=purokSelect('purok')?></label>
<div class="grid2"><label>Password<input type="password" name="password" placeholder="Create a password" required></label><label>Confirm password<input type="password" name="confirm" placeholder="Repeat password" required></label></div>
<label class="chk"><input type="checkbox" required> I agree to the Terms of Use and Data Privacy Notice</label>
<button class="btn primary block">Create Account</button><p class="center muted">Already registered? <a class="link" href="?p=login">Sign in</a></p></form>
<?php authShell(ob_get_clean()); break;

/* ---------- Dashboard ---------- */
case 'dashboard':
  $c = q("SELECT COUNT(*) t, SUM(status='Pending') p, SUM(status='In Progress') i, SUM(status='Resolved') r, SUM(created_at>=NOW()-INTERVAL 7 DAY) w FROM reports WHERE user_id=?", [$u['id']])->fetch();
  $rec = q('SELECT * FROM reports WHERE user_id=? ORDER BY id DESC LIMIT 4', [$u['id']])->fetchAll();
  $adv = q("SELECT * FROM announcements WHERE kind='Advisory' ORDER BY created_at DESC LIMIT 1")->fetch(); ?>
<div class="between"><div><h3 class="big">Magandang araw, <?=e(explode(' ',$u['full_name'])[0])?>!</h3><small class="muted">Here's what's happening in your community today.</small></div>
<div class="gap"><a class="btn" href="?p=map">View Hazard Map</a><a class="btn primary" href="?p=complaint">+ New Report</a></div></div>
<?php if($adv): ?><div class="banner warn"><b>🌧</b><div><strong><?=e($adv['title'])?></strong><small><?=e($adv['body'])?></small></div><a class="btn" href="?p=announcements">View details</a></div><?php endif; ?>
<div class="stats"><?php foreach([['Total Reports',$c['t'],'+'.(int)$c['w'].' this week','📄'],['Pending',(int)$c['p'],'Awaiting review','⏱'],['In Progress',(int)$c['i'],'Being handled','⚙'],['Resolved',(int)$c['r'],'Closed successfully','✔']] as $s): ?>
<div class="card stat"><small><?=$s[0]?></small><span class="ico"><?=$s[3]?></span><b><?=(int)$s[1]?></b><small><?=$s[2]?></small></div><?php endforeach; ?></div>
<div class="cols"><div class="card"><div class="between"><div><h4>Recent Reports</h4><small class="muted">Your latest complaints and disaster reports</small></div><a class="link" href="?p=reports">View all →</a></div>
<div class="table"><?php if($rec) reportRows($rec); else echo '<p class="empty">No reports yet. File your first report to see it here.</p>'; ?></div></div>
<div><div class="card"><h4>Quick Actions</h4><a class="qa" href="?p=complaint">📄<div><strong>Submit a Complaint</strong><small>Garbage, drainage, noise &amp; more</small></div>›</a><a class="qa red" href="?p=disaster">⚠<div><strong>Report a Disaster</strong><small>Flood, fire, typhoon, landslide</small></div>›</a><a class="qa green" href="?p=map">📍<div><strong>Open Hazard Map</strong><small>Evacuation centers &amp; risk zones</small></div>›</a></div>
<div class="card"><h4>Emergency Hotlines</h4><div class="hot"><span>National Emergency</span><b class="red">911</b></div><div class="hot"><span>MDRRMO Amadeo</span><b>09XX XXX XXXX</b></div><div class="hot"><span>Talon Barangay Hall</span><b>09XX XXX XXXX</b></div><small class="muted">Numbers other than 911 are placeholders — replace with official lines.</small></div></div></div>
<?php shell('Dashboard','Brgy. Talon, Amadeo, Cavite · '.date('l, F j, Y'),ob_get_clean()); break;

/* ---------- Submit complaint ---------- */
case 'complaint': ?>
<form method="post" enctype="multipart/form-data" class="cols form"><input type="hidden" name="a" value="complaint">
<div class="card orange"><h4>1. What is your complaint about?</h4><small class="muted">Choose the category that best matches your concern.</small>
<div class="chips"><?php foreach(['Garbage & Sanitation','Drainage & Flooding','Street Lighting','Noise Disturbance','Water Supply','Peace & Order'] as $i=>$cat): ?><label class="chip"><input type="radio" name="category" value="<?=$cat?>" <?=$i?'':'checked'?>><span><?=$cat?></span></label><?php endforeach; ?></div>
<h4>2. Describe the problem</h4><label>Subject<input name="subject" placeholder="Overflowing drainage along Purok 3" required></label>
<label>Description<textarea name="description" rows="4" placeholder="Describe what happened, when it started, and who is affected..." required></textarea></label>
<h4>3. Where is it located?</h4><small class="muted">Your barangay and municipality are pre-filled.</small>
<div class="grid2"><label>Barangay<select name="barangay"><option>Talon</option></select></label><label>Municipality<select name="municipality"><option>Amadeo, Cavite</option></select></label>
<label>Purok / Sitio<?=purokSelect('purok',$u['purok']??'')?></label><label>Landmark (optional)<input name="landmark" placeholder="e.g. beside Talon Covered Court"></label></div>
<h4>4. Add photos (optional)</h4><label class="drop" id="drop">⬆<strong>Click to upload or drag &amp; drop photos</strong><small>PNG or JPG · up to 10 MB each · maximum of 4 photos</small><input type="file" name="photos[]" id="photos" accept="image/png,image/jpeg" multiple hidden></label><div id="previews" class="previews"></div>
<div class="between tg"><div><strong>Submit anonymously</strong><small>Your name will be hidden from the public list of reports.</small></div><label class="sw"><input type="checkbox" name="anonymous"><i></i></label></div>
<div class="between tg"><div><strong>Send me SMS updates</strong><small>Get a text when the status of your report changes.</small></div><label class="sw"><input type="checkbox" name="sms" checked><i></i></label></div>
<div class="end gap"><a class="btn" href="?p=dashboard">Cancel</a><button class="btn primary">Submit Complaint</button></div></div>
<div><div class="card"><h4>Where your report goes</h4><div class="qa plain">📍<div><strong>Barangay Talon Hall</strong><small>Amadeo, Cavite 4119</small></div></div><div class="qa plain">⏱<div><strong>Typical response</strong><small>Within 48 hours</small></div></div><div class="qa plain">🛡<div><strong>Your data</strong><small>Protected under the Data Privacy Act</small></div></div></div>
<div class="card"><h4>Tips for a faster resolution</h4><ul class="ticks"><li>Be specific about the exact location</li><li>Attach a clear, recent photo</li><li>Include a contact number we can reach</li></ul></div>
<div class="banner danger col"><strong>Is this an emergency?</strong><small>For floods, fires, landslides or other disasters, use the disaster report form so responders are alerted right away.</small><a class="btn red block" href="?p=disaster">Report a Disaster</a></div></div></form>
<?php shell('Submit a Complaint','Tell Barangay Talon about a concern in your community',ob_get_clean()); break;

/* ---------- Report disaster ---------- */
case 'disaster': ?>
<div class="banner danger"><b>📞</b><div><strong>Life-threatening emergency? Call 911 first.</strong><small>Then submit this report so barangay responders can locate and assist you.</small></div><a class="btn red" href="tel:911">Call 911</a></div>
<form method="post" enctype="multipart/form-data" class="cols form"><input type="hidden" name="a" value="disaster"><input type="hidden" name="lat" id="lat"><input type="hidden" name="lng" id="lng"><input type="hidden" name="barangay" value="Talon"><input type="hidden" name="municipality" value="Amadeo, Cavite">
<div class="card"><h4>1. Type of incident</h4><small class="muted">Select what is happening.</small>
<div class="chips"><?php foreach(['Flood','Fire','Typhoon Damage','Landslide','Earthquake','Power Outage'] as $i=>$cat): ?><label class="chip red"><input type="radio" name="category" value="<?=$cat?>" <?=$i?'':'checked'?>><span><?=$cat?></span></label><?php endforeach; ?></div>
<h4>2. How severe is it?</h4><div class="chips pills"><?php foreach(['Low','Moderate','High','Critical'] as $s): ?><label class="chip red"><input type="radio" name="severity" value="<?=$s?>" <?=$s==='High'?'checked':''?>><span><?=$s?></span></label><?php endforeach; ?></div>
<div class="grid2"><label>People affected<input name="people_affected" placeholder="12 persons · 3 households"></label><label>Immediate need<select name="need"><option>Evacuation &amp; rescue</option><option>Medical assistance</option><option>Food &amp; water</option><option>Fire response</option><option>Road clearing</option></select></label></div>
<h4>3. Location</h4><small class="muted">Share exactly where help is needed.</small>
<div class="grid2"><label>Barangay<select disabled><option>Talon</option></select></label><label>Municipality<select disabled><option>Amadeo, Cavite</option></select></label><label>Purok / Sitio<?=purokSelect('purok',$u['purok']??'')?></label><label>Landmark<input name="landmark" placeholder="e.g. near the creek crossing"></label></div>
<button type="button" class="btn soft" id="pin">📍 Pin exact location on the map</button> <small id="pinmsg" class="muted"></small>
<h4>4. Details &amp; photos</h4><label>What is happening?<textarea name="description" rows="3" placeholder="Water is rising along the road and entering several houses..." required></textarea></label>
<label class="drop" id="drop">⬆<strong>Click to upload or drag &amp; drop photos</strong><small>PNG or JPG · up to 10 MB each · maximum of 4 photos</small><input type="file" name="photos[]" id="photos" accept="image/png,image/jpeg" multiple hidden></label><div id="previews" class="previews"></div>
<label>Contact number<input name="contact" value="<?=e($u['mobile']??'')?>" placeholder="09XX XXX XXXX" required></label>
<div class="between tg"><div><strong>I need immediate rescue assistance</strong><small>Responders will prioritize your report and may call you.</small></div><label class="sw"><input type="checkbox" name="rescue" checked><i></i></label></div>
<div class="end gap"><a class="btn" href="?p=dashboard">Cancel</a><button class="btn red">Send Emergency Report</button></div></div>
<div><div class="card navy"><small>NATIONAL EMERGENCY HOTLINE</small><b class="huge">911</b><small>Available 24/7 for police, fire and medical emergencies.</small></div>
<div class="card"><h4>Nearest evacuation centers</h4><?php foreach(q('SELECT * FROM evac_centers ORDER BY distance_km')->fetchAll() as $v): ?><div class="qa green">📍<div><strong><?=e($v['name'])?></strong><small>Capacity <?=$v['capacity']?> · <?=$v['distance_km']?> km away</small></div>›</div><?php endforeach; ?></div>
<div class="card"><h4>What happens next</h4><ol class="steps"><li>Barangay officials and MDRRMO are alerted instantly</li><li>A responder reviews and verifies your report</li><li>You get live status updates until it is resolved</li></ol></div></div></form>
<?php shell('Report a Disaster','Alert Barangay Talon and MDRRMO Amadeo about an incident',ob_get_clean()); break;

/* ---------- My reports ---------- */
case 'reports':
  $f = $_GET['s'] ?? ''; $pg = max(1,(int)($_GET['pg']??1)); $w = 'user_id=?'; $a = [$u['id']];
  if (in_array($f,['Pending','In Progress','Resolved'])) { $w.=' AND status=?'; $a[]=$f; }
  $c = q("SELECT COUNT(*) t, SUM(status='Pending') p, SUM(status='In Progress') i, SUM(status='Resolved') r FROM reports WHERE user_id=?",[$u['id']])->fetch();
  $total = q("SELECT COUNT(*) FROM reports WHERE $w",$a)->fetchColumn();
  $rows = q("SELECT * FROM reports WHERE $w ORDER BY id DESC LIMIT 8 OFFSET ".(($pg-1)*8),$a)->fetchAll(); ?>
<div class="between"><div class="tabs"><a class="<?=$f?'':'on'?>" href="?p=reports">All <?=(int)$c['t']?></a><a class="<?=$f==='Pending'?'on':''?>" href="?p=reports&s=Pending">Pending <?=(int)$c['p']?></a><a class="<?=$f==='In Progress'?'on':''?>" href="?p=reports&s=In Progress">In Progress <?=(int)$c['i']?></a><a class="<?=$f==='Resolved'?'on':''?>" href="?p=reports&s=Resolved">Resolved <?=(int)$c['r']?></a></div>
<div class="gap"><input class="search" id="rsearch" placeholder="Search by title or ID"><a class="btn primary" href="?p=complaint">+ New Report</a></div></div>
<div class="card table"><div class="row head"><span>REPORT</span><span>CATEGORY</span><span>LOCATION</span><span>STATUS</span><span>DATE</span><em></em></div>
<?php if($rows) reportRows($rows); else echo '<p class="empty">No reports match. Try another filter or file a new report.</p>'; ?>
<div class="between pad"><small class="muted">Showing <?=$rows?(($pg-1)*8+1).'–'.(($pg-1)*8+count($rows)):0?> of <?=$total?> reports</small><div class="pager"><?php for($i=1;$i<=max(1,ceil($total/8));$i++): ?><a class="<?=$i==$pg?'on':''?>" href="?p=reports&s=<?=urlencode($f)?>&pg=<?=$i?>"><?=$i?></a><?php endfor; ?></div></div></div>
<?php shell('My Reports','Track the status of every complaint and disaster report you filed',ob_get_clean()); break;

/* ---------- Report details ---------- */
case 'report':
  $r = q('SELECT * FROM reports WHERE id=?',[(int)($_GET['id']??0)])->fetch();
  if (!$r || (!$admin && $r['user_id']!=$u['id'])) { redir('reports'); }
  $ups = q('SELECT * FROM report_updates WHERE report_id=? ORDER BY id',[$r['id']])->fetchAll();
  $ph = q('SELECT * FROM report_photos WHERE report_id=?',[$r['id']])->fetchAll(); ?>
<a class="link" href="?p=<?=$admin?'admin':'reports'?>">← Back to <?=$admin?'Admin Console':'My Reports'?></a>
<div class="cols"><div class="card"><div class="gap"><?=badge($r['status'])?><span class="pill info"><?=e($r['category'])?></span></div><h3 class="big"><?=e($r['subject'])?></h3>
<div class="meta"><div><small>Report ID</small><b><?=e($r['ref_no'])?></b></div><div><small>Filed on</small><b><?=date('M d, Y · g:i A',strtotime($r['created_at']))?></b></div><div><small>Location</small><b><?=e($r['purok'].', Brgy. '.$r['barangay'].', '.$r['municipality'])?></b></div><div><small>Assigned to</small><b><?=e($r['assigned_to'])?></b></div></div>
<h4>Description</h4><p><?=nl2br(e($r['description']))?></p>
<h4>Attached photos</h4><div class="previews"><?php foreach($ph as $x): ?><img src="uploads/<?=e($x['file'])?>" alt="Attached photo"><?php endforeach; if(!$ph) echo '<small class="muted">No photos attached.</small>'; ?></div>
<?php if($admin): ?><form method="post" class="gap tg"><input type="hidden" name="a" value="status"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="status"><?php foreach(['Pending','In Progress','Urgent','Resolved'] as $s) echo '<option'.($s===$r['status']?' selected':'').'>'.$s.'</option>'; ?></select><button class="btn primary">Update status</button></form><?php endif; ?>
<h4>Add an update or comment</h4><form method="post"><input type="hidden" name="a" value="comment"><input type="hidden" name="id" value="<?=$r['id']?>"><label>Message<textarea name="message" rows="3" placeholder="Write a message to the barangay team..." required></textarea></label><div class="end"><button class="btn primary">Send Update</button></div></form></div>
<div><div class="card"><h4>Status timeline</h4><ul class="tl"><?php foreach($ups as $x): ?><li class="done"><strong><?=e($x['title'])?></strong><small><?=date('M d · g:i A',strtotime($x['created_at']))?></small><?php if($x['message']) echo '<p>'.e($x['message']).'</p>'; ?></li><?php endforeach; if($r['status']!=='Resolved') echo '<li class="next"><strong>Resolved</strong><small>Awaiting completion</small></li>'; ?></ul></div>
<div class="card"><h4>Need help with this report?</h4><small class="muted">Contact the Barangay Hall and mention your report ID.</small><a class="btn block" href="tel:911">Contact Barangay Hall</a></div></div></div>
<?php shell('Report Details',$r['ref_no'].' · Filed '.date('M d, Y',strtotime($r['created_at'])),ob_get_clean()); break;

/* ---------- Submitted ---------- */
case 'done':
  $r = q('SELECT * FROM reports WHERE id=?',[(int)($_GET['id']??0)])->fetch(); if(!$r) redir('dashboard');
  if(!$u): echo '<div class="authbox"><h2>Emergency report sent</h2><p>Reference: <b>'.e($r['ref_no']).'</b></p><a class="btn primary" href="?p=login">Back to sign in</a></div>'; authShell(ob_get_clean()); break; endif; ?>
<div class="card done"><b class="okc">✔</b><h3>Report submitted successfully</h3><p class="muted">Your report was sent to Barangay <?=e($r['barangay'])?>, Amadeo. We will notify you by SMS as soon as the status changes.</p>
<div class="meta col"><div class="between"><small>Reference no.</small><b class="link"><?=e($r['ref_no'])?></b></div><div class="between"><small>Category</small><b><?=e($r['category'])?></b></div><div class="between"><small>Location</small><b><?=e($r['purok'].', Brgy. '.$r['barangay'].', Amadeo')?></b></div><div class="between"><small>Submitted</small><b><?=date('M d, Y · g:i A',strtotime($r['created_at']))?></b></div><div class="between"><small>Status</small><b><?=e($r['status'])?> review</b></div></div>
<div class="gap center"><a class="btn" href="?p=dashboard">Back to Dashboard</a><a class="btn primary" href="?p=report&id=<?=$r['id']?>">Track my report</a></div></div>
<?php shell('Report Submitted','Thank you for helping keep Talon safe',ob_get_clean()); break;

/* ---------- Hazard map ---------- */
case 'map':
  $hz = q('SELECT * FROM hazards')->fetchAll(); $ev = q('SELECT * FROM evac_centers')->fetchAll();
  $act = q("SELECT * FROM reports WHERE type='disaster' AND status<>'Resolved' ORDER BY id DESC LIMIT 3")->fetchAll(); ?>
<div class="cols"><div class="card"><div class="gap layers"><b>Layers</b><span class="pill danger">Flood zones</span><span class="pill orange">Landslide zones</span><span class="pill ok">Evacuation centers</span><span class="pill">Reports</span></div>
<div class="map"><small class="mlabel">BRGY. TALON</small><div class="creek">Talon Creek</div><div class="road h"></div><div class="road v v1"></div><div class="road v v2"></div>
<?php foreach($hz as $h): ?><div class="zone <?=$h['kind']?>" style="left:<?=$h['pos_x']?>%;top:<?=$h['pos_y']?>%;width:<?=$h['size_w']?>%;height:<?=$h['size_h']?>%"><b class="mk <?=$h['kind']?>">⚠</b><small><?=e($h['label'])?></small></div><?php endforeach; ?>
<?php foreach($ev as $v): ?><b class="mk ok" style="left:<?=$v['pos_x']?>%;top:<?=$v['pos_y']?>%" title="<?=e($v['name'])?>">🛡</b><?php endforeach; ?>
<div class="legend"><small>LEGEND</small><span class="d red"></span>Flood-prone zone<span class="d orange"></span>Landslide-prone zone<span class="d green"></span>Evacuation center</div></div></div>
<div><div class="card"><h4>Active alerts</h4><div class="banner warn mini">⚠ <div><strong>Heavy rainfall advisory</strong><small>Amadeo, Cavite · Today</small></div></div>
<?php foreach($act as $x): ?><div class="banner danger mini">⚠ <div><strong><?=e($x['category'].' · '.$x['purok'])?></strong><small>Brgy. Talon · <?=date('M d',strtotime($x['created_at']))?></small></div></div><?php endforeach; ?></div>
<div class="card"><h4>Evacuation centers</h4><?php foreach($ev as $v): ?><div class="qa green">📍<div><strong><?=e($v['name'])?></strong><small>Capacity <?=$v['capacity']?> · <?=e($v['status'])?></small></div><b><?=$v['distance_km']?> km</b></div><?php endforeach; ?><a class="btn soft block" href="https://www.google.com/maps/search/evacuation+center+Amadeo+Cavite" target="_blank" rel="noopener">Get directions</a></div>
<div class="banner danger col"><strong>See a new hazard?</strong><small>Report floods, fallen trees or blocked roads so others stay safe.</small><a class="btn red block" href="?p=disaster">Report a Disaster</a></div></div></div>
<?php shell('Hazard & Evacuation Map','Brgy. Talon, Amadeo, Cavite · Sample risk zones',ob_get_clean()); break;

/* ---------- Announcements ---------- */
case 'announcements':
  $k = $_GET['k'] ?? ''; $rows = $k ? q('SELECT * FROM announcements WHERE kind=? ORDER BY created_at DESC',[$k])->fetchAll() : q('SELECT * FROM announcements ORDER BY created_at DESC')->fetchAll();
  $kc = ['Advisory'=>'danger','Notice'=>'info','Event'=>'ok','Health'=>'blue']; ?>
<div class="cols"><div><div class="tabs"><a class="<?=$k?'':'on'?>" href="?p=announcements">All</a><?php foreach(['Advisory'=>'Advisories','Notice'=>'Notices','Event'=>'Events'] as $x=>$l) echo '<a class="'.($k===$x?'on':'').'" href="?p=announcements&k='.$x.'">'.$l.'</a>'; ?></div>
<?php foreach($rows as $r): ?><article class="card ann"><div class="between"><span class="pill <?=$kc[$r['kind']]?>"><?=$r['kind']?></span><small class="muted">📅 <?=date('M d, Y',strtotime($r['created_at']))?></small></div><h4><?=e($r['title'])?></h4><p><?=e($r['body'])?></p><div class="between"><small class="muted">Posted by <?=e($r['posted_by'])?></small><a class="link" href="#">Read more →</a></div></article><?php endforeach; ?></div>
<div><div class="card"><h4>Emergency hotlines</h4><div class="hot"><span>National Emergency</span><b class="red">911</b></div><?php foreach(['MDRRMO Amadeo','Talon Barangay Hall','BFP Amadeo','PNP Amadeo'] as $h) echo '<div class="hot"><span>'.$h.'</span><b>09XX XXX XXXX</b></div>'; ?><small class="muted">Numbers other than 911 are placeholders.</small></div>
<div class="card"><h4>Alert preferences</h4><div class="between tg"><span>SMS alerts</span><label class="sw"><input type="checkbox" <?=$u['pref_sms']?'checked':''?> disabled><i></i></label></div><div class="between tg"><span>Email updates</span><label class="sw"><input type="checkbox" <?=$u['pref_status']?'checked':''?> disabled><i></i></label></div><a class="link" href="?p=profile">Change in Profile &amp; Settings</a></div></div></div>
<?php shell('Announcements & Alerts','Official updates from Barangay Talon and the Municipality of Amadeo',ob_get_clean()); break;

/* ---------- Profile ---------- */
case 'profile':
  $n = q('SELECT COUNT(*) t, SUM(status="Resolved") r FROM reports WHERE user_id=?',[$u['id']])->fetch(); ?>
<?php if(isset($_GET['saved'])): ?><div class="banner ok">Changes saved.</div><?php endif; if($err): ?><div class="err"><?=e($err)?></div><?php endif; ?>
<div class="cols side-l"><div><div class="card center"><b class="av big"><?=e($ini)?></b><h4><?=e($u['full_name'])?></h4><small class="muted"><?=$admin?'Admin':'Resident'?> · Brgy. <?=e($u['barangay'])?>, <?=e($u['municipality'])?> <?=e($u['postal_code'])?></small><br><span class="pill ok">Verified resident</span><div class="grid2"><div class="card stat"><b><?=(int)$n['t']?></b><small>Reports</small></div><div class="card stat"><b><?=(int)$n['r']?></b><small>Resolved</small></div></div></div></div>
<div><form method="post" class="card"><input type="hidden" name="a" value="profile"><h4>Personal information</h4><small class="muted">Keep your details current so responders can reach you.</small>
<div class="grid2"><label>Full name<input name="full_name" value="<?=e($u['full_name'])?>" required></label><label>Mobile number<input name="mobile" value="<?=e($u['mobile'])?>" placeholder="09XX XXX XXXX"></label></div>
<label>Email address<input type="email" name="email" value="<?=e($u['email'])?>" required></label>
<div class="grid2"><label>Barangay<select disabled><option>Talon</option></select></label><label>Municipality<select disabled><option>Amadeo, Cavite</option></select></label><label>Purok / Sitio<?=purokSelect('purok',$u['purok'])?></label><label>Postal code<input name="postal_code" value="<?=e($u['postal_code'])?>"></label></div>
<h4>Notifications</h4><div class="between tg"><div><strong>SMS disaster alerts</strong><small>Receive flood, fire and typhoon alerts for Talon and Amadeo.</small></div><label class="sw"><input type="checkbox" name="pref_sms" <?=$u['pref_sms']?'checked':''?>><i></i></label></div>
<div class="between tg"><div><strong>Report status updates</strong><small>Email me when the status of my report changes.</small></div><label class="sw"><input type="checkbox" name="pref_status" <?=$u['pref_status']?'checked':''?>><i></i></label></div>
<div class="between tg"><div><strong>Barangay announcements</strong><small>Notices, events and schedules from the Barangay Council.</small></div><label class="sw"><input type="checkbox" name="pref_announce" <?=$u['pref_announce']?'checked':''?>><i></i></label></div>
<div class="end gap"><a class="btn" href="?p=profile">Cancel</a><button class="btn primary">Save Changes</button></div></form>
<form method="post" class="card"><input type="hidden" name="a" value="password"><h4>Security</h4><div class="grid2"><label>Current password<input type="password" name="old" required></label><label>New password<input type="password" name="new" minlength="8" required></label></div><button class="btn">Change password</button></form></div></div>
<?php shell('Profile & Settings','Manage your personal information and preferences',ob_get_clean()); break;

/* ---------- Admin console ---------- */
case 'admin':
  $s = q("SELECT SUM(DATE(created_at)=CURDATE()) n, SUM(status='Pending') p, SUM(status='In Progress') i, SUM(status='Resolved' AND MONTH(created_at)=MONTH(NOW())) r FROM reports")->fetch();
  $f = $_GET['f'] ?? ''; $w = $f==='complaint'||$f==='disaster' ? "WHERE type='$f'" : ($f==='urgent' ? "WHERE priority='Urgent'" : '');
  $rows = q("SELECT * FROM reports $w ORDER BY FIELD(priority,'Urgent','High','Medium','Low'), id DESC LIMIT 20")->fetchAll();
  $cat = q('SELECT category, COUNT(*) n FROM reports GROUP BY category ORDER BY n DESC LIMIT 5')->fetchAll(); $max = max(1,$cat?$cat[0]['n']:1);
  $hot = q('SELECT purok, COUNT(*) n FROM reports WHERE purok<>"" GROUP BY purok ORDER BY n DESC LIMIT 3')->fetchAll(); ?>
<div class="stats"><?php foreach([['New today',$s['n'],'Filed since midnight','⚠'],['Awaiting review',$s['p'],'Needs assignment','⏱'],['In progress',$s['i'],'Assigned to teams','⚙'],['Resolved this month',$s['r'],'Closed reports','✔']] as $x): ?><div class="card stat"><small><?=$x[0]?></small><span class="ico"><?=$x[3]?></span><b><?=(int)$x[1]?></b><small><?=$x[2]?></small></div><?php endforeach; ?></div>
<div class="cols"><div class="card"><div class="between"><h4>Incoming reports</h4><div class="tabs mini"><a class="<?=$f?'':'on'?>" href="?p=admin">All</a><a class="<?=$f==='complaint'?'on':''?>" href="?p=admin&f=complaint">Complaints</a><a class="<?=$f==='disaster'?'on':''?>" href="?p=admin&f=disaster">Disasters</a><a class="<?=$f==='urgent'?'on':''?>" href="?p=admin&f=urgent">Urgent</a></div></div>
<div class="table"><div class="row head a"><span>REPORT</span><span>TYPE</span><span>PUROK</span><span>PRIORITY</span><span>STATUS</span><em></em></div><?php foreach($rows as $r): ?><div class="row a"><div><strong><?=e($r['subject'])?></strong><small><?=e($r['ref_no'])?></small></div><span class="pill <?=$r['type']==='disaster'?'danger':'info'?>"><?=ucfirst($r['type'])?></span><span><?=e($r['purok'])?></span><?=prio($r['priority'])?><?=badge($r['status'])?><a class="btn soft" href="?p=report&id=<?=$r['id']?>">Review</a></div><?php endforeach; if(!$rows) echo '<p class="empty">No incoming reports.</p>'; ?></div></div>
<div><div class="card"><h4>Reports by category</h4><?php foreach($cat as $c): ?><div class="bar"><div class="between"><small><?=e($c['category'])?></small><small><?=$c['n']?></small></div><i style="width:<?=round($c['n']/$max*100)?>%"></i></div><?php endforeach; ?></div>
<div class="card"><h4>Hotspot puroks</h4><?php foreach($hot as $i=>$h): ?><div class="hot"><span><b class="rk"><?=$i+1?></b> <?=e($h['purok'])?></span><small><?=$h['n']?> reports</small></div><?php endforeach; ?></div></div></div>
<?php shell('Admin Console','Manage and respond to reports from Talon and Amadeo',ob_get_clean()); break;
endswitch; ?>
?>
<script src="assets/app.js"></script></body></html>
