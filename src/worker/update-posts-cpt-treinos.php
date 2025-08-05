<?php
set_time_limit(0);

// Carrega as configurações
require_once(__DIR__ . '/config.php');
require_once __DIR__ . '/taxonomy-mapping.php';

// Inicializa o arquivo de log
$logFile = get_log_file_path();
file_put_contents($logFile, '');

// Função de log
function log_message($message, $level = 'INFO')
{
    global $logFile;
    $timestamp = date('Y-m-d H:i:s');
    
    // Emojis para diferentes níveis
    $level_icons = [
        'INFO' => 'ℹ️',
        'SUCCESS' => '✅',
        'ERROR' => '❌',
        'WARNING' => '⚠️',
        'PROGRESS' => '🔄',
        'COMPLETE' => '🎯',
        'HEADER' => '📋',
        'SECTION' => '📁'
    ];
    
    $icon = $level_icons[$level] ?? 'ℹ️';
    $message_with_timestamp = "[$timestamp] $icon $message";

    if (php_sapi_name() !== 'cli') {
        echo "<script>console.log(" . json_encode($message_with_timestamp) . ");</script>";
    } else {
        error_log($message_with_timestamp . "\n", 3, $logFile);
    }
}

// Função para log de separador
function log_separator($title = '') {
    if (!empty($title)) {
        log_message("=== $title ===", 'HEADER');
    } else {
        log_message("==========================================", 'HEADER');
    }
}

// Função para log de progresso
function log_progress($current, $total, $message = '') {
    $percentage = $total > 0 ? round(($current / $total) * 100, 1) : 0;
    $progress_bar = str_repeat('█', round($percentage / 5)) . str_repeat('░', 20 - round($percentage / 5));
    $progress_text = "[$progress_bar] $current/$total ($percentage%)";
    
    if (!empty($message)) {
        log_message("$progress_text - $message", 'PROGRESS');
    } else {
        log_message($progress_text, 'PROGRESS');
    }
}

// Função para obter vídeos do canal e verificar tags de importação (OTIMIZADA)
function getChannelVideosForImport() {
    $channel_id = YOUTUBE_CHANNEL_ID;
    $import_tag = IMPORT_TAG;
    $videos_to_import = [];
    $page_token = '';
    $total_videos_checked = 0;
    
    log_message("Iniciando busca otimizada de vídeos do canal", 'INFO');
    
    do {
        $url = get_youtube_channel_search_url($channel_id, $page_token);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            throw new Exception("Erro ao buscar vídeos do canal: $error_msg");
        }
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code != 200) {
            throw new Exception("Erro ao buscar vídeos do canal: HTTP $http_code");
        }
        
        $data = json_decode($response);
        if (!$data || !isset($data->items)) {
            break;
        }
        
        // Coleta todos os IDs de vídeo desta página
        $video_ids = [];
        $video_info = [];
        
        foreach ($data->items as $item) {
            $video_id = $item->id->videoId;
            $video_ids[] = $video_id;
            $video_info[$video_id] = [
                'title' => $item->snippet->title ?? '',
                'published_at' => $item->snippet->publishedAt ?? ''
            ];
        }
        
        $total_videos_checked += count($video_ids);
        log_message("Verificando página com " . count($video_ids) . " vídeos (Total verificado: $total_videos_checked)", 'INFO');
        
        // Verifica tags em lote (máximo 50 vídeos por vez - limite da API)
        if (!empty($video_ids)) {
            $batch_results = checkImportTagsBatch($video_ids, $import_tag);
            
            foreach ($batch_results as $video_id => $has_tag) {
                if ($has_tag) {
                    $videos_to_import[] = [
                        'id' => $video_id,
                        'title' => $video_info[$video_id]['title'],
                        'published_at' => $video_info[$video_id]['published_at']
                    ];
                }
            }
        }
        
        $page_token = $data->nextPageToken ?? '';
        
    } while (!empty($page_token));
    
    log_message("Busca concluída: $total_videos_checked vídeos verificados, " . count($videos_to_import) . " com tag de importação", 'SUCCESS');
    
    return $videos_to_import;
}

// Função para verificar tags em lote (OTIMIZADA)
function checkImportTagsBatch($video_ids, $import_tag) {
    $results = [];
    $batch_size = 50; // Máximo permitido pela API do YouTube
    $batches = array_chunk($video_ids, $batch_size);
    
    log_message("Verificando tags em " . count($batches) . " lotes de até $batch_size vídeos", 'INFO');
    
    foreach ($batches as $batch_index => $batch) {
        $video_ids_str = implode(',', $batch);
        $url = get_youtube_api_url_batch($video_ids_str);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPGET, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response === false) {
            log_message("Erro ao verificar lote " . ($batch_index + 1), 'ERROR');
            continue;
        }
        
        if ($http_code !== 200) {
            log_message("❌ Erro HTTP $http_code no lote " . ($batch_index + 1), 'ERROR');
            if ($http_code === 403) {
                log_message("🚫 Quota da API excedida! Aguarde o reset.", 'WARNING');
                return $results; // Para de processar se quota excedida
            }
            continue;
        }
        
        $data = json_decode($response);
        if (!$data || !isset($data->items)) {
            log_message("Resposta inválida no lote " . ($batch_index + 1), 'WARNING');
            continue;
        }
        
        foreach ($data->items as $item) {
            $video_id = $item->id;
            $tags = $item->snippet->tags ?? [];
            $results[$video_id] = in_array($import_tag, $tags);
        }
        
        log_message("Lote " . ($batch_index + 1) . " verificado: " . count($batch) . " vídeos", 'INFO');
        
        // Delay para evitar exceder quota
        if (defined('API_DELAY_SECONDS') && API_DELAY_SECONDS > 0) {
            sleep(API_DELAY_SECONDS);
        }
    }
    
    return $results;
}



// Função para verificar se um vídeo já existe no WordPress
function videoExistsInWordPress($video_id) {
    global $wpdb;
    
    $post = $wpdb->get_row($wpdb->prepare("
        SELECT p.ID, pm2.meta_value as last_update
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
        LEFT JOIN {$wpdb->postmeta} pm2 ON p.ID = pm2.post_id AND pm2.meta_key = 'last_api_update'
        WHERE p.post_type = %s 
        AND p.post_status = %s
        AND pm.meta_key = 'id_video'
        AND pm.meta_value = %s
    ", CPT_NAME, CPT_STATUS, $video_id));
    
    if ($post) {
        // Verifica se foi atualizado recentemente (cache)
        if ($post->last_update) {
            $last_update = new DateTime($post->last_update);
            $now = new DateTime();
            $diff_hours = ($now->getTimestamp() - $last_update->getTimestamp()) / 3600;
            
            if ($diff_hours < CACHE_DURATION_HOURS) {
                log_message("⏭️ Vídeo $video_id já atualizado recentemente (cache válido)", 'INFO');
                return true; // Considera como existente se cache válido
            }
        }
    }
    
    return $post !== null;
}

// Função para contar total de posts para processar
function getTotalPostsToProcess() {
    global $wpdb;
    
    $count = $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(*)
        FROM {$wpdb->posts} p
        WHERE p.post_type = %s 
        AND p.post_status = %s
    ", CPT_NAME, CPT_STATUS));
    
    return intval($count);
}

// Função para criar um novo post apenas com o ID do vídeo
function createVideoPost($video_id, $title = '') {
    $post_data = array(
        'post_title' => $title ?: "Vídeo $video_id",
        'post_content' => '',
        'post_status' => CPT_STATUS,
        'post_type' => CPT_NAME,
        'post_author' => 1
    );
    
    $post_id = wp_insert_post($post_data);
    
    if (is_wp_error($post_id)) {
        throw new Exception("Erro ao criar post: " . $post_id->get_error_message());
    }
    
    // Salva apenas o ID do vídeo
    update_field('id_video', $video_id, $post_id);
    
    log_message("Novo post criado: ID $post_id para vídeo $video_id", 'SUCCESS');
    return $post_id;
}

// Função para obter dados do vídeo diretamente da API do YouTube
function getYoutubeVideoData($id_video)
{
    $youtubeApi = get_youtube_api_url($id_video);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $youtubeApi);
    curl_setopt($ch, CURLOPT_HTTPGET, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        $error_msg = curl_error($ch);
        curl_close($ch);
        throw new Exception("Erro ao obter dados do vídeo '$id_video': $error_msg");
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code != 200) {
        if ($http_code === 403) {
            throw new Exception("Quota da API excedida! Aguarde o reset da quota.");
        }
        throw new Exception("Erro ao obter dados do vídeo '$id_video': HTTP $http_code");
    }
    
    $youtubeData = json_decode($response);
    if (!$youtubeData || !isset($youtubeData->items) || empty($youtubeData->items)) {
        throw new Exception("Vídeo '$id_video' não encontrado ou dados inválidos");
    }
    
    $video = $youtubeData->items[0];
    $snippet = $video->snippet;
    $statistics = $video->statistics;
    $contentDetails = $video->contentDetails;
    
    // Processa duração
    $duration = $contentDetails->duration ?? 'PT0S';
    $duracao_em_segundos = convertDurationToSeconds($duration);
    $duracao_formatada = formatDuration($duration);
    
    // Processa views
    $views = $statistics->viewCount ?? 0;
    $views_formatadas = formatNumber($views);
    
    // Processa likes
    $likes = $statistics->likeCount ?? 0;
    
    // Processa data de publicação
    $data_publicacao = new DateTime($snippet->publishedAt);
    $data_publicacao->setTimezone(new DateTimeZone('America/Sao_Paulo'));
    $data_formatada = $data_publicacao->format('d/m/Y H:i:s');
    $data_curta = $data_publicacao->format('d/m/Y');
    
    // Processa thumbnail - sempre usa maxresdefault.jpg
    $thumbnail_url = "https://img.youtube.com/vi/" . $id_video . "/maxresdefault.jpg";
    
        // Processa tags do vídeo
    $tags = $snippet->tags ?? array();
    
    // Extrai título curto das tags
    $titulo_curto = extractTituloCurtoFromTags($tags);

    return (object)[
        "Id" => $id_video,
        "Titulo" => $snippet->title ?? '',
        "TituloCurto" => $titulo_curto,
        "IdCanal" => $snippet->channelId ?? '',
        "Canal" => $snippet->channelTitle ?? '',
        "Url" => "https://www.youtube.com/watch?v=" . $id_video,
        "ThumbnailUrl" => $thumbnail_url,
        "DataPublicacao" => $data_formatada,
        "DataPublicacaoCurto" => $data_curta,
        "Duracao" => $duracao_formatada,
        "DuracaoEmSegundos" => $duracao_em_segundos,
        "Views" => $views,
        "ViewsCurto" => $views_formatadas,
        "Likes" => $likes,
        "Plataforma" => "Youtube",
        "Tags" => $tags
    ];
}

// Função para converter duração ISO 8601 para segundos
function convertDurationToSeconds($duration) {
    $interval = new DateInterval($duration);
    return ($interval->h * 3600) + ($interval->i * 60) + $interval->s;
}

// Função para extrair título curto das tags do YouTube
function extractTituloCurtoFromTags($tags) {
    $prefix = TITULO_CURTO_TAG_PREFIX;
    
    foreach ($tags as $tag) {
        $tag = trim($tag);
        if (stripos($tag, $prefix) === 0) {
            $titulo_curto = trim(substr($tag, strlen($prefix)));
            if (!empty($titulo_curto)) {
                log_message("Título curto extraído: '$titulo_curto'", 'INFO');
                return $titulo_curto;
            }
        }
    }
    
    return ''; // Retorna vazio se não encontrar
}

// Função para formatar duração
function formatDuration($duration) {
    $interval = new DateInterval($duration);
    $hours = $interval->h;
    $minutes = $interval->i;
    $seconds = $interval->s;
    
    if ($hours > 0) {
        return sprintf("%02d:%02d:%02d", $hours, $minutes, $seconds);
    } else {
        return sprintf("%02d:%02d", $minutes, $seconds);
    }
}

// Função para processar o mapping de taxonomias/termos
function process_taxonomy_mapping($post_id, $youtube_tags) {
    $mapping = include __DIR__ . '/taxonomy-mapping.php';
    
    $processed_terms = [];
    $skipped_tags = [];
    $errors = [];
    
    foreach ($youtube_tags as $tag) {
        $tag = strtolower(trim($tag));
        if (isset($mapping[$tag])) {
            list($taxonomy, $term_slug) = $mapping[$tag];
            
            if (taxonomy_exists($taxonomy)) {
                $term_obj = get_term_by('slug', $term_slug, $taxonomy);
                if ($term_obj && !is_wp_error($term_obj)) {
                    wp_set_object_terms($post_id, $term_obj->term_id, $taxonomy, true);
                    $processed_terms[] = [
                        'tag' => $tag,
                        'taxonomy' => $taxonomy,
                        'term_name' => $term_obj->name,
                        'term_slug' => $term_slug
                    ];
                } else {
                    $errors[] = "Termo com slug '{$term_slug}' não existe na taxonomia '{$taxonomy}' para a tag '{$tag}'";
                }
            } else {
                $errors[] = "Taxonomia '{$taxonomy}' não existe para a tag '{$tag}'";
            }
        } else {
            $skipped_tags[] = $tag;
        }
    }
    
    // Log detalhado dos termos processados
    if (!empty($processed_terms)) {
        log_message("=== TAXONOMIAS APLICADAS PARA O POST {$post_id} ===", 'HEADER');
        foreach ($processed_terms as $term) {
            log_message("Tag '{$term['tag']}' → Taxonomia '{$term['taxonomy']}' → Termo '{$term['term_name']}' (slug: {$term['term_slug']})", 'SUCCESS');
        }
        log_message("Total de termos aplicados: " . count($processed_terms), 'SUCCESS');
    }
    
    // Log de tags ignoradas
    if (!empty($skipped_tags)) {
        log_message("Tags ignoradas (não mapeadas): " . implode(', ', $skipped_tags), 'WARNING');
    }
    
    // Log de erros
    if (!empty($errors)) {
        foreach ($errors as $error) {
            log_message($error, 'ERROR');
        }
    }
    
    // Resumo final
    $total_tags = count($youtube_tags);
    $total_processed = count($processed_terms);
    $total_skipped = count($skipped_tags);
    $total_errors = count($errors);
    
    log_message("=== RESUMO TAXONOMIAS POST {$post_id} ===", 'HEADER');
    log_message("📊 Tags do YouTube: {$total_tags}", 'INFO');
    log_message("📊 Termos aplicados: {$total_processed}", 'SUCCESS');
    log_message("📊 Tags ignoradas: {$total_skipped}", 'WARNING');
    log_message("📊 Erros: {$total_errors}", 'ERROR');
    log_separator();
}

// Função para formatar números (views, likes)
function formatNumber($number) {
    // Converte para inteiro se for string
    $number = intval($number);
    
    if ($number >= 1000000000) {
        $formatted = round($number / 1000000000, 1);
        // Remove .0 se for número inteiro
        $formatted = ($formatted == floor($formatted)) ? floor($formatted) : $formatted;
        return $formatted . ' bi';
    } elseif ($number >= 1000000) {
        $formatted = round($number / 1000000, 1);
        // Remove .0 se for número inteiro
        $formatted = ($formatted == floor($formatted)) ? floor($formatted) : $formatted;
        return $formatted . ' mi';
    } elseif ($number >= 1000) {
        $formatted = round($number / 1000, 1);
        // Remove .0 se for número inteiro
        $formatted = ($formatted == floor($formatted)) ? floor($formatted) : $formatted;
        return $formatted . ' mil';
    } else {
        return (string)$number;
    }
}

// Atualiza os metadados do CPT diretamente no banco de dados
function updatePostType($id_post, $videoData)
{
    global $wpdb;
    
    // Atualiza o título do post
    $wpdb->update(
        $wpdb->posts,
        array('post_title' => $videoData->Titulo),
        array('ID' => $id_post),
        array('%s'),
        array('%d')
    );
    
    // Atualiza os metadados ACF
    $acf_fields = array(
        'url_video' => $videoData->Url,
        'canal' => $videoData->Canal,
        'id_canal' => $videoData->IdCanal,
        'url_thumbnail' => $videoData->ThumbnailUrl,
        'data_publicacao_video' => $videoData->DataPublicacao,
        'data_publicacao_video_curto' => $videoData->DataPublicacaoCurto,
        'duracao_do_video' => $videoData->Duracao,
        'duracao_do_video_em_segundos' => $videoData->DuracaoEmSegundos,
        'views' => $videoData->Views,
        'views_curto' => $videoData->ViewsCurto,
        'likes' => $videoData->Likes,
        'plataforma' => $videoData->Plataforma
    );
    
    // Só atualiza o título curto se houver um valor válido
    if (!empty($videoData->TituloCurto)) {
        $acf_fields['titulo_video_curto'] = $videoData->TituloCurto;
    }
    
    foreach ($acf_fields as $field_name => $field_value) {
        update_field($field_name, $field_value, $id_post);
    }
    
    // Registra data da última atualização via API
    update_post_meta($id_post, 'last_api_update', current_time('mysql'));
    
    // Limpa o cache do post
    clean_post_cache($id_post);
    
    log_message("Post '$id_post' atualizado no banco de dados", 'SUCCESS');

    if (!empty($videoData->Tags) && is_array($videoData->Tags)) {
        process_taxonomy_mapping($id_post, $videoData->Tags);
    }
}

// Retorna o post com base na página diretamente do banco
function getPostType($contador)
{
    global $wpdb;
    
    $offset = ($contador - 1) * POSTS_PER_PAGE;
    
    $post = $wpdb->get_row($wpdb->prepare("
        SELECT p.ID, p.post_title, p.post_status
        FROM {$wpdb->posts} p
        WHERE p.post_type = %s 
        AND p.post_status = %s
        ORDER BY p.ID ASC
        LIMIT %d OFFSET %d
    ", CPT_NAME, CPT_STATUS, POSTS_PER_PAGE, $offset));
    
    if (!$post) {
        return null;
    }
    
    // Busca os metadados ACF
    $post->meta = new stdClass();
    
    // Busca campos ACF específicos
    $acf_fields = array(
        'id_video' => get_field('id_video', $post->ID),
        'url_video' => get_field('url_video', $post->ID),
        'canal' => get_field('canal', $post->ID),
        'id_canal' => get_field('id_canal', $post->ID),
        'url_thumbnail' => get_field('url_thumbnail', $post->ID),
        'data_publicacao_video' => get_field('data_publicacao_video', $post->ID),
        'data_publicacao_video_curto' => get_field('data_publicacao_video_curto', $post->ID),
        'duracao_do_video' => get_field('duracao_do_video', $post->ID),
        'duracao_do_video_em_segundos' => get_field('duracao_do_video_em_segundos', $post->ID),
        'views' => get_field('views', $post->ID),
        'views_curto' => get_field('views_curto', $post->ID),
        'likes' => get_field('likes', $post->ID),
        'plataforma' => get_field('plataforma', $post->ID),
        'titulo_video_curto' => get_field('titulo_video_curto', $post->ID)
    );
    
    // Converte para o formato esperado
    foreach ($acf_fields as $key => $value) {
        $post->meta->$key = $value;
    }
    
    return $post;
}

// Define imagem destacada usando thumbnail do vídeo
function setFeaturedImage($post_id, $imageUrl)
{
    global $wpdb;
    
    // Verifica se já tem imagem destacada
    $featured_media = get_post_meta($post_id, '_thumbnail_id', true);
    if ($featured_media) {
        log_message("Post '$post_id' já possui imagem destacada", 'INFO');
        return;
    }

    // Usar diretório temporário configurado
    $tmpDir = get_temp_dir_path();
    if (!is_dir($tmpDir)) {
        mkdir($tmpDir, 0755, true);
        log_message("Diretório tmp criado: $tmpDir", 'INFO');
    }

    $temp_file = tempnam($tmpDir, TEMP_FILE_PREFIX);
    $image_data = file_get_contents($imageUrl);
    if ($image_data === false) {
        throw new Exception("Erro ao baixar imagem da URL: $imageUrl");
    }
    file_put_contents($temp_file, $image_data);
    log_message("Imagem salva temporariamente em: $temp_file", 'INFO');

    // Obtém o título do post para usar no nome do arquivo
    $post_title = get_the_title($post_id);
    $slugifiedTitle = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $post_title), '-'));
    $filename = $slugifiedTitle . '.jpg';
    
    // Define o caminho de upload do WordPress
    $upload_dir = wp_upload_dir();
    $target_path = $upload_dir['path'] . '/' . $filename;
    
    // Move o arquivo para o diretório de uploads
    if (!copy($temp_file, $target_path)) {
        unlink($temp_file);
        throw new Exception("Erro ao mover imagem para diretório de uploads");
    }
    
    // Apaga arquivo temporário
    unlink($temp_file);
    log_message("Imagem temporária apagada: $temp_file", 'INFO');
    
    // Obtém informações do arquivo
    $file_type = wp_check_filetype($filename, null);
    $attachment = array(
        'post_mime_type' => $file_type['type'],
        'post_title' => sanitize_file_name($filename),
        'post_content' => '',
        'post_status' => 'inherit'
    );
    
    // Insere o attachment no banco
    $attach_id = wp_insert_attachment($attachment, $target_path, $post_id);
    
    if (is_wp_error($attach_id)) {
        throw new Exception("Erro ao inserir attachment: " . $attach_id->get_error_message());
    }
    
    // Gera os tamanhos da imagem
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $attach_data = wp_generate_attachment_metadata($attach_id, $target_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    
    // Define como imagem destacada
    set_post_thumbnail($post_id, $attach_id);
    
    log_message("Imagem destacada definida para o post '$post_id' como '$filename'", 'SUCCESS');
}

// Protege acesso via navegador
if (!is_valid_access_key()) {
        die('Acesso negado.');
}

// Carrega o WordPress
function find_wp_load() {
    // Se o caminho manual estiver definido, use-o
    if (!empty(WP_LOAD_PATH) && file_exists(WP_LOAD_PATH)) {
        return WP_LOAD_PATH;
    }
    
    $current_dir = __DIR__;
    $max_levels = 10; // Máximo de níveis para procurar
    
    for ($i = 0; $i < $max_levels; $i++) {
        $wp_load_path = $current_dir . '/wp-load.php';
        if (file_exists($wp_load_path)) {
            return $wp_load_path;
        }
        
        $current_dir = dirname($current_dir);
        if ($current_dir === dirname($current_dir)) {
            break; // Chegou na raiz
        }
    }
    
    // Se não encontrou, tenta caminhos comuns
    $common_paths = array(
        dirname(dirname(dirname(dirname(__FILE__)))) . '/wp-load.php',
        dirname(dirname(dirname(__FILE__))) . '/wp-load.php',
        dirname(dirname(__FILE__)) . '/wp-load.php',
        dirname(__FILE__) . '/wp-load.php'
    );
    
    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    throw new Exception("Não foi possível encontrar wp-load.php. Verifique se o worker está na pasta correta ou configure WP_LOAD_PATH no config.php");
}

$wp_load_path = find_wp_load();
require_once($wp_load_path);

// Controle de iterações
$contador = 1;
$maxIterations = get_max_iterations();

// Execução principal
try {
    // FASE 1: Importação de novos vídeos do canal
    log_separator("FASE 1: VERIFICANDO NOVOS VÍDEOS DO CANAL");
    
    try {
        log_message("Buscando vídeos do canal: " . YOUTUBE_CHANNEL_ID, 'INFO');
        log_message("Tag de importação: " . IMPORT_TAG, 'INFO');
        
        $videos_to_import = getChannelVideosForImport();
        $total_videos = count($videos_to_import);
        
        if ($total_videos == 0) {
            log_message("Nenhum vídeo encontrado com a tag de importação", 'WARNING');
        } else {
            log_message("Encontrados $total_videos vídeos com tag de importação no canal", 'SUCCESS');
            
            $imported_count = 0;
            $skipped_count = 0;
            
            foreach ($videos_to_import as $index => $video) {
                $video_id = $video['id'];
                $title = $video['title'];
                
                log_progress($index + 1, $total_videos, "Verificando vídeo: $video_id");
                
                if (videoExistsInWordPress($video_id)) {
                    log_message("Vídeo $video_id já existe no WordPress - pulando", 'WARNING');
                    $skipped_count++;
                } else {
                    createVideoPost($video_id, $title);
                    log_message("Novo post criado para vídeo $video_id", 'SUCCESS');
                    $imported_count++;
                }
            }
            
            log_separator("RESUMO FASE 1");
            log_message("📊 Novos posts criados: $imported_count", 'SUCCESS');
            log_message("📊 Vídeos já existiam: $skipped_count", 'INFO');
            log_message("📊 Total processado: $total_videos", 'INFO');
        }
        
    } catch (Exception $e) {
        log_message("Erro na fase de importação: " . $e->getMessage(), 'ERROR');
    }
    
    // FASE 2: Atualização de dados dos vídeos existentes
    log_separator("FASE 2: ATUALIZANDO DADOS DOS VÍDEOS");
    
    // Primeiro, conta quantos posts existem para processar
    $total_posts = getTotalPostsToProcess();
    if ($total_posts == 0) {
        log_message("Nenhum post encontrado para atualizar", 'WARNING');
    } else {
        log_message("Encontrados $total_posts posts para atualizar", 'SUCCESS');
        
        $contador = 1;
        $processed_count = 0;
        $error_count = 0;
        $error_posts = []; // Array para coletar IDs com erro
        
        while ($maxIterations === -1 || $contador <= $maxIterations) {
            $treino = getPostType($contador);
            if ($treino === null) {
                break;
            }
            
            if (!isset($treino->meta->id_video) || empty($treino->meta->id_video)) {
                log_message("Post '$treino->ID' não possui id_video - pulando", 'WARNING');
                $contador++;
                continue;
            }
            
            $id_video = $treino->meta->id_video;
            log_progress($contador, $total_posts, "Processando post ID: $treino->ID, Vídeo: $id_video");

            try {
                $youtubeVideoData = getYoutubeVideoData($id_video);
                updatePostType($treino->ID, $youtubeVideoData);
                setFeaturedImage($treino->ID, $youtubeVideoData->ThumbnailUrl);
                log_message("Post '$treino->ID' atualizado com sucesso", 'SUCCESS');
                $processed_count++;
            } catch (Exception $e) {
                log_message("Erro ao processar post '$treino->ID': " . $e->getMessage(), 'ERROR');
                $error_count++;
                $error_posts[] = $treino->ID; // Adiciona ID à lista de erros
            }

            $contador++;
        }

        log_separator("RESUMO FASE 2");
        log_message("📊 Posts processados com sucesso: $processed_count", 'SUCCESS');
        log_message("📊 Posts com erro: $error_count", 'ERROR');
        
        // Mostra os IDs dos posts com erro
        if (!empty($error_posts)) {
            $error_ids = implode(', ', $error_posts);
            log_message("📊 IDs dos posts com erro: $error_ids", 'ERROR');
        }
        
        log_message("📊 Total de posts: $total_posts", 'INFO');
    }

    log_separator("PROCESSAMENTO COMPLETO FINALIZADO");
    log_message("🎯 Worker executado com sucesso!", 'COMPLETE');
} catch (Exception $e) {
    log_message("Erro geral: " . $e->getMessage(), 'ERROR');
}
?>
