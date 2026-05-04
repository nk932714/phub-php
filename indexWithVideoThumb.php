<?php
/**
 * EDUCATIONAL HLS PROXY - ANIMATED THUMBNAILS & SEARCH
 * Optimized for May 2026 Autoplay Policies
 */

error_reporting(0); 

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    $_SERVER['SERVER_PORT'] == 443 ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);
$protocol = $isHttps ? "https://" : "http://";
$selfUrl = $protocol . $_SERVER['HTTP_HOST'] . explode('?', $_SERVER['REQUEST_URI'])[0];

// --- 1. PROXY ENGINE ---
if (isset($_GET['proxy_url'])) {
    $videoUrl = $_GET['proxy_url'];
    $baseDir = substr($videoUrl, 0, strrpos($videoUrl, '/') + 1);
    $ch = curl_init($videoUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_REFERER, 'https://www.pornhub.com/');
    $response = curl_exec($ch);
    curl_close($ch);

    header("Access-Control-Allow-Origin: *");
    if (strpos($response, '#EXTM3U') !== false) {
        header("Content-Type: application/vnd.apple.mpegurl");
        $lines = explode("\n", $response);
        $rewritten = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            if ($line[0] === '#') {
                if (strpos($line, 'URI="') !== false) {
                    $line = preg_replace_callback('/URI="([^"]+)"/', function($m) use ($baseDir, $selfUrl) {
                        $path = (strpos($m[1], 'http') === 0) ? $m[1] : $baseDir . $m[1];
                        return 'URI="' . $selfUrl . '?proxy_url=' . urlencode($path) . '"';
                    }, $line);
                }
                $rewritten[] = $line;
            } else {
                $fullPath = (strpos($line, 'http') === 0) ? $line : $baseDir . $line;
                $rewritten[] = $selfUrl . "?proxy_url=" . urlencode($fullPath);
            }
        }
        echo implode("\n", $rewritten);
    } else {
        header("Content-Type: video/mp2t");
        echo $response;
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Educational Video API</title>
    <link href="https://vjs.zencdn.net/8.3.0/video-js.css" rel="stylesheet" />
    <style>
        body { background: #0f0f0f; color: #fff; font-family: 'Inter', sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 1200px; margin: auto; }
        .header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 30px; gap: 20px; }
        .logo a { color: #f90; font-size: 24px; font-weight: bold; text-decoration: none; }
        .logo span { color: #fff; }
        
        .search-form { flex-grow: 1; display: flex; }
        .search-form input { flex-grow: 1; padding: 12px 20px; border-radius: 25px 0 0 25px; border: 1px solid #333; background: #1a1a1a; color: #fff; outline: none; }
        .search-form button { padding: 12px 25px; border-radius: 0 25px 25px 0; border: none; background: #f90; color: #000; font-weight: bold; cursor: pointer; }
        
        /* Grid & Cards */
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 20px; }
        .v-card { background: #1a1a1a; border-radius: 12px; overflow: hidden; border: 1px solid #222; transition: 0.2s; text-decoration: none; color: inherit; position: relative; }
        .v-card:hover { border-color: #f90; z-index: 10; transform: scale(1.05); box-shadow: 0 10px 30px rgba(0,0,0,0.7); }
        
        .v-thumb { width: 100%; aspect-ratio: 16/9; background: #000; position: relative; overflow: hidden; }
        .static-thumb { width: 100%; height: 100%; object-fit: cover; transition: opacity 0.3s; z-index: 1; position: relative; }
        
        .preview-video { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; transition: opacity 0.3s; z-index: 2; pointer-events: none; }
        
        .v-card:hover .preview-video { opacity: 1; }
        .v-card:hover .static-thumb { opacity: 0; }

        .v-duration { position: absolute; bottom: 8px; right: 8px; background: rgba(0,0,0,0.8); padding: 2px 6px; font-size: 11px; border-radius: 4px; z-index: 3; }
        .v-meta { padding: 12px; }
        .v-title { font-size: 14px; font-weight: 600; line-height: 1.4; height: 40px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
        
        .player-section { margin-bottom: 40px; background: #000; padding: 10px; border-radius: 15px; border: 1px solid #333; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <div class="logo"><a href="?">VIDEO<span>API</span></a></div>
        <form class="search-form" action="" method="GET">
            <input type="text" name="search" placeholder="Search videos..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
            <button type="submit">SEARCH</button>
        </form>
    </div>

    <?php
    // --- 2. VIDEO PLAYER VIEW ---
    if (isset($_GET['vkey'])) {
        $vkey = preg_replace('/[^a-z0-9]/i', '', $_GET['vkey']);
        $html = fetch("https://www.pornhub.com/view_video.php?viewkey=" . $vkey);
        if (preg_match('/flashvars_\d+\s*=\s*(?<json>{.*?});/is', $html, $matches)) {
            $data = json_decode($matches['json'], true);
            $videoUrl = $data['mediaDefinitions'][0]['videoUrl'] ?? "";
            if ($videoUrl) {
                $proxyPath = $selfUrl . "?proxy_url=" . urlencode($videoUrl);
                echo "<div class='player-section'><video id='player' class='video-js vjs-fluid vjs-big-play-centered' controls autoplay preload='auto'><source src='$proxyPath' type='application/x-mpegURL'></video></div>";
                echo "<script src='https://vjs.zencdn.net/8.3.0/video.min.js'></script><script>videojs('player', {html5: {vhs: {overrideNative: true}}});</script>";
            }
        }
    }

    // --- 3. FETCHING CONTENT (SEARCH OR HOME) ---
    $isSearch = !empty($_GET['search']);
    $requestPath = $isSearch ? "/video/search?search=" . urlencode($_GET['search']) : "/";
    $content = fetch("https://www.pornhub.com" . $requestPath);

    // Regex modified to ensure it captures thumb and mediabook correctly
    $pattern = '/data-video-vkey="(?<vkey>[^"]+)".*?src="(?<thumb>[^"]+)".*?data-mediabook="(?<animated>[^"]+)".*?title="(?<title>[^"]+)".*?(?:<var class="duration">(?<duration>[^<]+)<\/var>)?/is';

    echo "<h3>" . ($isSearch ? "Results for: " . htmlspecialchars($_GET['search']) : "Trending Now") . "</h3>";
    echo "<div class='grid'>";

    if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $item) {
            $watchUrl = "?vkey=" . $item['vkey'] . ($isSearch ? "&search=".urlencode($_GET['search']) : "");
            ?>
            <a href="<?php echo $watchUrl; ?>" class="v-card">
                <div class="v-thumb">
                    <img class="static-thumb" src="<?php echo $item['thumb']; ?>" alt="static thumbnail">
                    <video class="preview-video" muted loop playsinline preload="none" poster="<?php echo $item['thumb']; ?>">
                        <source src="<?php echo $item['animated']; ?>" type="video/mp4">
                    </video>
                    <?php if(!empty($item['duration'])): ?>
                        <span class="v-duration"><?php echo $item['duration']; ?></span>
                    <?php endif; ?>
                </div>
                <div class="v-meta">
                    <div class="v-title"><?php echo htmlspecialchars_decode($item['title']); ?></div>
                </div>
            </a>
            <?php
        }
    } else {
        echo "<div style='grid-column: 1/-1; text-align: center; padding: 50px;'>No results found.</div>";
    }
    echo "</div>";

    function fetch($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36');
        curl_setopt($ch, CURLOPT_COOKIE, "age_verified=1; platform=pc;");
        $res = curl_exec($ch);
        curl_close($ch);
        return $res;
    }
    ?>
</div>

<!-- JavaScript to handle autoplay logic correctly -->
<script>
    document.querySelectorAll('.v-card').forEach(card => {
        const video = card.querySelector('.preview-video');
        
        card.addEventListener('mouseenter', () => {
            // Trigger play on hover
            if (video.readyState >= 2) { // Checks if video is loaded enough to play
                video.play().catch(e => console.log("Playback prevented"));
            } else {
                video.load(); // Load if it wasn't preloaded
                video.play().catch(e => console.log("Playback prevented"));
            }
        });

        card.addEventListener('mouseleave', () => {
            video.pause();
            video.currentTime = 0; // Reset to beginning
        });
    });
</script>

</body>
</html>
