<?php
// index.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function getAccessToken() {


    $client_id = "";
    $client_secret = "";


    $url = "https://exbo.net/oauth/token";

    $data = [
        "grant_type" => "client_credentials",
        "client_id" => $client_id,
        "client_secret" => $client_secret,
        "scope" => ""
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    $result = json_decode($response, true);
    return $result['access_token'] ?? null;
}

function searchClans($token, $region, $search) {
    $allClans = [];
    $offset = 0;
    $limit = 200;
    $foundClans = [];
    
    // Сначала ищем в первой странице, если не находим - грузим дальше
    while (true) {
        $url = "https://eapi.stalcraft.net/{$region}/clans?limit={$limit}&offset={$offset}";
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $token
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            break;
        }
        
        $data = json_decode($response, true);
        
        if (!isset($data['data']) || empty($data['data'])) {
            break;
        }
        
        // Ищем в текущей странице
        foreach ($data['data'] as $clan) {
            if (stripos($clan['name'], $search) !== false || stripos($clan['tag'], $search) !== false) {
                $foundClans[] = $clan;
            }
        }
        
        // Если нашли хотя бы 10 совпадений или это последняя страница
        if (count($foundClans) >= 10 || count($data['data']) < $limit) {
            break;
        }
        
        $offset += $limit;
        usleep(300000); // 0.3 сек задержка
    }
    
    return $foundClans;
}

$token = getAccessToken();
$searchResult = null;
$error = null;

if (!$token) {
    $error = "Не удалось получить токен авторизации. Проверьте подключение.";
}

// Обработка поиска
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['clan_name']) && !empty($_POST['clan_name'])) {
    $clanSearch = trim($_POST['clan_name']);
    $region = $_POST['region'] ?? 'ru';
    
    if ($token) {
        $searchResult = searchClans($token, $region, $clanSearch);
        if (empty($searchResult)) {
            $error = "Клан \"{$clanSearch}\" не найден в регионе " . strtoupper($region);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Поиск клана Stalcraft</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
            min-height: 100vh;
            padding: 20px;
            color: #eee;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: rgba(255,255,255,0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.3);
        }
        
        h1 {
            text-align: center;
            margin-bottom: 10px;
            font-size: 2em;
            background: linear-gradient(135deg, #ff6b6b, #4ecdc4);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .subtitle {
            text-align: center;
            color: #aaa;
            margin-bottom: 30px;
        }
        
        .search-form {
            background: rgba(0,0,0,0.3);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #4ecdc4;
        }
        
        input, select {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            background: rgba(255,255,255,0.9);
            transition: all 0.3s;
        }
        
        input:focus, select:focus {
            outline: none;
            box-shadow: 0 0 0 2px #4ecdc4;
            background: white;
        }
        
        button {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #ff6b6b, #4ecdc4);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        button:hover {
            transform: translateY(-2px);
        }
        
        .error {
            background: rgba(255,107,107,0.2);
            border-left: 4px solid #ff6b6b;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            color: #ff6b6b;
        }
        
        .results {
            margin-top: 20px;
        }
        
        .result-card {
            background: rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 15px;
            transition: transform 0.2s;
            border-left: 4px solid #4ecdc4;
        }
        
        .result-card:hover {
            transform: translateX(5px);
            background: rgba(255,255,255,0.15);
        }
        
        .clan-name {
            font-size: 20px;
            font-weight: bold;
            color: #ff6b6b;
            margin-bottom: 8px;
        }
        
        .clan-tag {
            display: inline-block;
            background: rgba(78,205,196,0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 12px;
            margin-left: 10px;
            color: #4ecdc4;
        }
        
        .clan-id {
            font-family: monospace;
            background: rgba(0,0,0,0.5);
            padding: 8px;
            border-radius: 6px;
            margin: 10px 0;
            font-size: 12px;
            word-break: break-all;
        }
        
        .clan-info {
            display: flex;
            gap: 20px;
            margin-top: 10px;
            font-size: 14px;
            color: #ccc;
        }
        
        .clan-info span {
            color: #4ecdc4;
            font-weight: bold;
        }
        
        .copy-btn {
            background: #4ecdc4;
            color: #1a1a2e;
            border: none;
            padding: 5px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 12px;
            margin-top: 8px;
        }
        
        .copy-btn:hover {
            background: #ff6b6b;
            color: white;
        }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #4ecdc4;
            color: #1a1a2e;
            padding: 10px 20px;
            border-radius: 8px;
            animation: fadeOut 2s forwards;
        }
        
        @keyframes fadeOut {
            0% { opacity: 1; }
            70% { opacity: 1; }
            100% { opacity: 0; visibility: hidden; }
        }
        
        .loading {
            text-align: center;
            padding: 20px;
            font-size: 18px;
            color: #4ecdc4;
        }
        
        .stats {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            color: #888;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 Stalcraft Clan Finder</h1>
        <div class="subtitle">Найдите ID клана по названию или тегу</div>
        
        <div class="search-form">
            <form method="POST" id="searchForm">
                <div class="form-group">
                    <label>🏷️ Название или тег клана</label>
                    <input type="text" name="clan_name" placeholder="Например: Valiant, VNG, VALIANT" required value="<?php echo htmlspecialchars($_POST['clan_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label>🌍 Регион</label>
                    <select name="region">
                        <option value="ru" <?php echo ($_POST['region'] ?? 'ru') == 'ru' ? 'selected' : ''; ?>>🇷🇺 Россия (RU)</option>
                        <option value="eu" <?php echo ($_POST['region'] ?? '') == 'eu' ? 'selected' : ''; ?>>🇪🇺 Европа (EU)</option>
                        <option value="us" <?php echo ($_POST['region'] ?? '') == 'us' ? 'selected' : ''; ?>>🇺🇸 США (US)</option>
                        <option value="sea" <?php echo ($_POST['region'] ?? '') == 'sea' ? 'selected' : ''; ?>>🌏 Юго-Восточная Азия (SEA)</option>
                    </select>
                </div>
                
                <button type="submit" id="searchBtn">🔎 Найти клан</button>
            </form>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                ⚠️ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($searchResult !== null && !empty($searchResult)): ?>
            <div class="results">
                <h3>📋 Результаты поиска (<?php echo count($searchResult); ?> совпадений)</h3>
                <?php foreach ($searchResult as $clan): ?>
                    <div class="result-card">
                        <div class="clan-name">
                            <?php echo htmlspecialchars($clan['name']); ?>
                            <span class="clan-tag">[<?php echo htmlspecialchars($clan['tag']); ?>]</span>
                        </div>
                        
                        <div class="clan-id">
                            🆔 ID: <strong><?php echo htmlspecialchars($clan['id']); ?></strong>
                            <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($clan['id']); ?>')">📋 Копировать ID</button>
                        </div>
                        
                        <div class="clan-info">
                            <div>⭐ Уровень: <span><?php echo $clan['level']; ?></span></div>
                            <div>👥 Участников: <span><?php echo $clan['memberCount']; ?></span></div>
                            <div>📅 Создан: <span><?php echo date('d.m.Y', strtotime($clan['registrationTime'])); ?></span></div>
                        </div>
                        
                        <?php if (!empty($clan['leader'])): ?>
                            <div class="clan-info">
                                <div>👑 Лидер: <span><?php echo htmlspecialchars($clan['leader']); ?></span></div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($clan['alliance'])): ?>
                            <div class="clan-info">
                                <div>🤝 Альянс: <span><?php echo htmlspecialchars($clan['alliance']); ?></span></div>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php elseif ($_SERVER['REQUEST_METHOD'] === 'POST'): ?>
            <div class="error">
                🔍 Клан не найден. Попробуйте:
                <ul style="margin-top: 10px; margin-left: 20px;">
                    <li>Проверить правильность написания</li>
                    <li>Использовать только часть названия (например: "Val" вместо "Valiant")</li>
                    <li>Попробовать другой регион</li>
                    <li>Поискать по тегу клана</li>
                </ul>
            </div>
        <?php endif; ?>
        
        <div class="stats">
            💡 Подсказка: ID клана нужен для получения списка участников через API<br>
            🔗 Используйте полученный ID в других инструментах для сбора данных
        </div>
    </div>
    
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                // Создаем уведомление
                const toast = document.createElement('div');
                toast.className = 'toast';
                toast.textContent = '✅ ID скопирован: ' + text;
                document.body.appendChild(toast);
                
                setTimeout(() => {
                    toast.remove();
                }, 2000);
            });
        }
        
        // Анимация загрузки
        document.getElementById('searchForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('searchBtn');
            if (btn) {
                btn.textContent = '🔍 Поиск...';
                btn.disabled = true;
            }
        });
    </script>
</body>
</html>
