<?php
// Configurações do Worker de Atualização de Vídeos

// Segurança
define('WORKER_SECRET_KEY', 'processarvideos');

// CPT
define('CPT_NAME', 'treinos-video');
define('CPT_STATUS', 'publish');

// Execução
define('MAX_ITERATIONS_DEFAULT', 0); // 0 = infinito
define('POSTS_PER_PAGE', 1);

// Controle de quota da API
define('API_DELAY_SECONDS', 5); // Delay entre requisições para evitar quota
define('BATCH_SIZE', 50); // Máximo de vídeos por requisição batch
define('CACHE_DURATION_HOURS', 24); // Cache de vídeos já processados

// Log e arquivos
define('LOG_FILENAME', 'log.txt');
define('TEMP_DIR_NAME', 'tmp');
define('TEMP_FILE_PREFIX', 'ytthumb_');

// Campos ACF
define('ACF_FIELDS', array(
    'url_video', 'canal', 'id_canal', 'url_thumbnail',
    'data_publicacao_video', 'data_publicacao_video_curto',
    'duracao_do_video', 'duracao_do_video_em_segundos',
    'views', 'views_curto', 'likes', 'plataforma', 'titulo_video_curto'
));

// API YouTube
define('GOOGLE_API_KEY', '');
define('YOUTUBE_API_ENDPOINT', 'https://www.googleapis.com/youtube/v3/videos');

// Canal e filtro de importação
define('YOUTUBE_CHANNEL_ID', 'UCq3SqZJxKud5gisxMD0yZzg'); // ID do canal para monitorar
define('IMPORT_TAG', 'wp-importar'); // Tag que indica se o vídeo deve ser importado

// Configuração de título curto
define('TITULO_CURTO_TAG_PREFIX', 'wp-titulo:'); // Prefixo da tag para título curto

// Configurações
define('DEBUG_MODE', false);
define('FORCE_HTTPS_ONLY', false);
define('WP_LOAD_PATH', '');

// Funções auxiliares
function get_log_file_path() {
    return __DIR__ . '/' . LOG_FILENAME;
}

function get_temp_dir_path() {
    return __DIR__ . '/' . TEMP_DIR_NAME;
}

function get_site_base_url() {
    if (FORCE_HTTPS_ONLY) {
        return "https://" . $_SERVER['HTTP_HOST'];
    } else {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
        return $protocol . "://" . $_SERVER['HTTP_HOST'];
    }
}

function get_youtube_api_url($video_id) {
    return YOUTUBE_API_ENDPOINT . "?part=snippet,statistics,contentDetails&id=" . urlencode($video_id) . "&key=" . GOOGLE_API_KEY;
}

function get_youtube_api_url_batch($video_ids) {
    return YOUTUBE_API_ENDPOINT . "?part=snippet&id=" . urlencode($video_ids) . "&key=" . GOOGLE_API_KEY;
}

function get_youtube_channel_search_url($channel_id, $page_token = '') {
    $url = "https://www.googleapis.com/youtube/v3/search?part=snippet&channelId=" . urlencode($channel_id) . "&order=date&type=video&maxResults=50&key=" . GOOGLE_API_KEY;
    if (!empty($page_token)) {
        $url .= "&pageToken=" . urlencode($page_token);
    }
    return $url;
}

function is_debug_mode() {
    return DEBUG_MODE;
}

function get_max_iterations() {
    $max_iterations = MAX_ITERATIONS_DEFAULT;
    
    if (php_sapi_name() !== 'cli') {
        if (isset($_GET['max'])) {
            $max_iterations = intval($_GET['max']);
        }
    } else {
        $options = getopt("", ["max:"]);
        if (isset($options['max'])) {
            $max_iterations = intval($options['max']);
        }
    }
    
    return ($max_iterations === 0) ? -1 : $max_iterations;
}

function is_valid_access_key() {
    if (php_sapi_name() !== 'cli') {
        return isset($_GET['chave']) && $_GET['chave'] === WORKER_SECRET_KEY;
    }
    return true;
}
?> 
