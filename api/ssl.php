<?php
/**
 * WP Hosting Panel - SSL Certificate API
 * ========================================
 * POST /api/ssl?action=install   - Install SSL certificate
 * GET  /api/ssl?action=list     - List all SSL certs
 * GET  /api/ssl?action=status   - Check SSL status for domain
 * POST /api/ssl?action=renew    - Renew certificate
 * POST /api/ssl?action=remove   - Remove certificate
 */

require_once __DIR__ . '/../config.php';
header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'install':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleInstall();
        break;
    case 'list':
        handleList();
        break;
    case 'status':
        handleStatus();
        break;
    case 'renew':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleRenew();
        break;
    case 'remove':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') jsonResponse(['success' => false, 'error' => 'POST required'], 405);
        handleRemove();
        break;
    default:
        jsonResponse(['success' => false, 'error' => 'Unknown action'], 400);
}

function handleInstall(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');
    $email = input('email', $user['email'] ?? 'admin@example.com');
    $autoRenew = input('auto_renew', true);

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM websites WHERE id = ? AND (user_id = ? OR ? = 'admin')");
    $stmt->execute([$websiteId, $user['id'], $user['role']]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $domain = $website['domain'];

    // Check if SSL already active
    $stmt2 = $db->prepare("SELECT id FROM ssl_certs WHERE website_id = ? AND status = 'active'");
    $stmt2->execute([$websiteId]);
    if ($stmt2->fetch()) {
        jsonResponse(['success' => false, 'error' => 'SSL already active for this domain'], 400);
    }

    // Check certbot
    exec("which certbot 2>&1", $out, $ret);
    if ($ret !== 0) {
        jsonResponse(['success' => false, 'error' => 'Certbot not installed. Run: apt install certbot'], 500);
    }

    // Use certbot standalone mode
    $log = [];

    // Stop OpenLiteSpeed temporarily for standalone mode
    $log[] = ['step' => 'Stopping OpenLiteSpeed for standalone SSL...', 'status' => 'running'];
    exec("systemctl stop lsws 2>&1");

    $log[] = ['step' => 'Requesting SSL certificate...', 'status' => 'running'];
    $cmd = "certbot certonly --standalone -d {$domain} -d www.{$domain} --non-interactive --agree-tos -m {$email} 2>&1";
    exec($cmd, $out, $ret);
    $log[] = ['step' => 'Certbot execution', 'status' => $ret === 0 ? 'done' : 'error', 'output' => implode("\n", $out)];

    // Restart OpenLiteSpeed
    exec("systemctl start lsws 2>&1", $out2, $ret2);
    $log[] = ['step' => 'OpenLiteSpeed restart', 'status' => $ret2 === 0 ? 'done' : 'error', 'output' => implode("\n", $out2)];

    // Update LiteSpeed vhost XML to add SSL
    if ($ret === 0) {
        $vhostDir = LSWS_VHOSTS_DIR . '/' . $domain;
        $vhostConf = $vhostDir . '/vhost.conf';
        if (file_exists($vhostConf)) {
            $vhostXml = file_get_contents($vhostConf);
            $sslBlock = <<<SSL
  <ssl>
    <certFile>/etc/letsencrypt/live/{$domain}/fullchain.pem</certFile>
    <keyFile>/etc/letsencrypt/live/{$domain}/privkey.pem</keyFile>
    <certChain>1</certChain>
    <enableECDHE>1</enableECDHE>
    <sslProtocol>TLSv1.2 TLSv1.3</sslProtocol>
  </ssl>
SSL;
            // Insert SSL block before closing tag
            $vhostXml = str_replace('</virtualHostConfig>', $sslBlock . "\n</virtualHostConfig>", $vhostXml);
            file_put_contents($vhostConf, $vhostXml);
            exec(LSWS_BIN . ' restart 2>&1');
        }
    }

    if ($ret !== 0) {
        $db->prepare("UPDATE websites SET ssl_status = 'none' WHERE id = ?")->execute([$websiteId]);
        jsonResponse([
            'success' => false,
            'error' => 'SSL certificate installation failed. Domain may not point to this server yet.',
            'output' => implode("\n", $out),
        ], 500);
    }

    // Get certificate expiry
    $expiryDate = '';
    $certPath = SSL_CERTS_DIR . '/' . $domain . '/cert.pem';
    if (file_exists($certPath)) {
        exec("openssl x509 -in {$certPath} -noout -enddate 2>&1", $out, $ret2);
        if ($ret2 === 0 && preg_match('/notAfter=(.*)/', $out[0], $matches)) {
            $expiryDate = date('Y-m-d', strtotime($matches[1]));
        }
    }

    // Save to database
    $certId = generateId('ssl');
    $db->prepare("INSERT INTO ssl_certs (id, website_id, domain, issuer, expiry_date, auto_renew, status) VALUES (?, ?, ?, ?, ?, ?, ?)")
        ->execute([$certId, $websiteId, $domain, "Let's Encrypt", $expiryDate, $autoRenew ? 1 : 0, 'active']);

    $db->prepare("UPDATE websites SET ssl_status = 'active', ssl_expiry = ? WHERE id = ?")
        ->execute([$expiryDate, $websiteId]);

    logActivity($user['id'], 'SSL Installed', "SSL certificate installed for {$domain}", 'success');

    jsonResponse([
        'success' => true,
        'domain' => $domain,
        'issuer' => "Let's Encrypt",
        'expiry_date' => $expiryDate,
        'auto_renew' => (bool) $autoRenew,
        'https_url' => "https://{$domain}",
        'log' => $log,
        'message' => "SSL certificate installed successfully for {$domain}",
    ]);
}

function handleList(): void {
    $user = authMiddleware();
    $db = getDB();

    if ($user['role'] === 'admin') {
        $stmt = $db->query("
            SELECT sc.*, w.domain, w.user_id
            FROM ssl_certs sc
            LEFT JOIN websites w ON sc.website_id = w.id
            ORDER BY sc.created_at DESC
        ");
    } else {
        $stmt = $db->prepare("
            SELECT sc.*, w.domain, w.user_id
            FROM ssl_certs sc
            LEFT JOIN websites w ON sc.website_id = w.id
            WHERE w.user_id = ?
            ORDER BY sc.created_at DESC
        ");
        $stmt->execute([$user['id']]);
    }

    jsonResponse(['success' => true, 'certificates' => $stmt->fetchAll()]);
}

function handleStatus(): void {
    $user = authMiddleware();
    $domain = sanitizeDomain($_GET['domain'] ?? '');

    if (!$domain) jsonResponse(['success' => false, 'error' => 'Domain required'], 400);

    $certPath = SSL_CERTS_DIR . '/' . $domain . '/cert.pem';

    if (!file_exists($certPath)) {
        jsonResponse(['success' => true, 'status' => 'none', 'message' => 'No SSL certificate']);
    }

    $result = ['domain' => $domain];

    exec("openssl x509 -in {$certPath} -noout -subject 2>&1", $out, $ret);
    $result['subject'] = $ret === 0 ? trim($out[0]) : '';

    exec("openssl x509 -in {$certPath} -noout -issuer 2>&1", $out, $ret);
    $result['issuer'] = $ret === 0 ? trim($out[0]) : '';

    exec("openssl x509 -in {$certPath} -noout -enddate 2>&1", $out, $ret);
    if ($ret === 0 && preg_match('/notAfter=(.*)/', $out[0], $matches)) {
        $expiry = strtotime($matches[1]);
        $result['expiry_date'] = date('Y-m-d', $expiry);
        $result['days_remaining'] = (int) ceil(($expiry - time()) / 86400);
        $result['status'] = $expiry > time() ? 'active' : 'expired';
    }

    exec("openssl x509 -in {$certPath} -noout -dates 2>&1", $out, $ret);
    $result['dates'] = $ret === 0 ? $out : [];

    jsonResponse(['success' => true, 'certificate' => $result]);
}

function handleRenew(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT w.domain FROM websites w JOIN ssl_certs sc ON w.id = sc.website_id WHERE w.id = ? AND sc.status = 'active'");
    $stmt->execute([$websiteId]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'No active SSL found'], 404);

    $domain = $website['domain'];
    exec("certbot renew --cert-name {$domain} --non-interactive 2>&1", $out, $ret);

    if ($ret === 0) {
        exec("systemctl restart lsws 2>&1");
        $newExpiry = '';
        $certPath = SSL_CERTS_DIR . '/' . $domain . '/cert.pem';
        if (file_exists($certPath)) {
            exec("openssl x509 -in {$certPath} -noout -enddate 2>&1", $out2, $ret2);
            if ($ret2 === 0 && preg_match('/notAfter=(.*)/', $out2[0], $matches)) {
                $newExpiry = date('Y-m-d', strtotime($matches[1]));
            }
        }
        if ($newExpiry) {
            $db->prepare("UPDATE ssl_certs SET expiry_date = ? WHERE website_id = ?")->execute([$newExpiry, $websiteId]);
            $db->prepare("UPDATE websites SET ssl_expiry = ? WHERE id = ?")->execute([$newExpiry, $websiteId]);
        }
    }

    logActivity($user['id'], 'SSL Renewed', "SSL renewed for {$domain}", 'success');

    jsonResponse([
        'success' => $ret === 0,
        'message' => $ret === 0 ? "SSL renewed for {$domain}" : "SSL renewal failed",
        'output' => implode("\n", $out),
    ]);
}

function handleRemove(): void {
    $user = authMiddleware();
    $websiteId = input('website_id');

    if (!$websiteId) jsonResponse(['success' => false, 'error' => 'Website ID required'], 400);

    $db = getDB();
    $stmt = $db->prepare("SELECT domain FROM websites WHERE id = ?");
    $stmt->execute([$websiteId]);
    $website = $stmt->fetch();
    if (!$website) jsonResponse(['success' => false, 'error' => 'Website not found'], 404);

    $domain = $website['domain'];
    exec("certbot delete --cert-name {$domain} --non-interactive 2>&1", $out, $ret);

    $db->prepare("UPDATE ssl_certs SET status = 'none' WHERE website_id = ?")->execute([$websiteId]);
    $db->prepare("UPDATE websites SET ssl_status = 'none', ssl_expiry = NULL WHERE id = ?")->execute([$websiteId]);

    logActivity($user['id'], 'SSL Removed', "SSL removed from {$domain}", 'warning');

    jsonResponse(['success' => true, 'message' => "SSL certificate removed from {$domain}"]);
}

function logActivity(string $userId, string $action, string $desc, string $type): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO activity_log (id, user_id, action, description, type, ip_address) VALUES (?, ?, ?, ?, ?, ?)")
            ->execute([generateId('a'), $userId, $action, $desc, $type, $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (Exception $e) {}
}
