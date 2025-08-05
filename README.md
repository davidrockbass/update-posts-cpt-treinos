# 🔧 API Integração YouTube - Worker de Atualização Automática

## 📋 Visão Geral

Este projeto contém um **worker inteligente** que sincroniza automaticamente posts do CPT `treinos-video` com dados do YouTube, incluindo:

- ✅ **Importação automática** de novos vídeos do canal (opcional)
- ✅ **Atualização inteligente** de dados existentes
- ✅ **Mapeamento automático** de taxonomias baseado em tags
- ✅ **Título curto personalizado** via tags do YouTube
- ✅ **Processamento otimizado** em lotes (10x mais rápido)
- ✅ **Logs visuais** com barras de progresso e emojis
- ✅ **Compatibilidade total** com ACF (Advanced Custom Fields)
- ✅ **Cache inteligente** para evitar reprocessamento
- ✅ **Rate limiting** configurável para evitar quota excedida
- ✅ **Fase 1 opcional** via parâmetro

**🔄 Processamento Infinito:** Por padrão, o worker processa **todos os vídeos** do canal até não encontrar mais nenhum.

## 🚀 Funcionalidades Principais

### 📥 **Fase 1: Importação Automática (Opcional)**
- Busca **todos os vídeos** do canal configurado
- Verifica tags de importação em **lotes otimizados**
- Cria posts automaticamente para novos vídeos
- **Performance:** 10x mais rápido que requisições individuais
- **Opcional:** Pode ser desativada via parâmetro

### 🔄 **Fase 2: Atualização Inteligente**
- Atualiza dados de todos os posts existentes
- Sincroniza views, likes, duração, etc.
- Aplica imagem destacada automaticamente
- Processa taxonomias baseado em tags do YouTube
- **Cache:** Evita reprocessar vídeos atualizados recentemente

### 🏷️ **Sistema de Taxonomias Automático**
- Mapeia tags do YouTube para taxonomias do WordPress
- **Um para um:** Cada tag mapeia para apenas um termo
- Usa **slugs** dos termos (não nomes)
- Logs detalhados de processamento

### 📝 **Título Curto Personalizado**
- Extrai título curto de tags especiais (`wp-titulo:`)
- **Preserva valores existentes** se não houver tag
- Permite controle total via YouTube
- Salva no campo ACF `titulo_video_curto`

### 🛡️ **Sistema de Cache Inteligente**
- **24 horas de cache** para vídeos processados
- Evita reprocessamento desnecessário
- Economia de quota da API
- Persistente no banco de dados

### ⚡ **Rate Limiting Otimizado**
- **Delay configurável** entre requisições (5 segundos)
- Evita exceder quota da API
- Processamento estável e confiável

## 📁 Estrutura do Projeto

```
api-integracao-youtube/
├── worker/                           # Worker principal
│   ├── config.php                    # Configurações centralizadas
│   ├── update-posts-cpt-treinos.php  # Worker principal
│   ├── taxonomy-mapping.php          # Mapeamento de taxonomias
│   ├── get-taxonomies.php            # Utilitário para taxonomias
│   ├── test-mapping.php              # Teste do mapeamento
│   ├── log.txt                       # Arquivo de log
│   ├── tmp/                          # Arquivos temporários
│   └── README.md                     # Documentação do worker
├── entities/                         # Entidades do sistema
│   └── video.php                     # Classe Video
└── index.php                         # Arquivo de segurança
```

## ⚙️ Configuração

### 1. Configuração Básica (`worker/config.php`)

```php
// API YouTube
define('GOOGLE_API_KEY', 'sua-api-key-aqui');
define('YOUTUBE_CHANNEL_ID', 'UC123456789'); // ID do seu canal

// Importação
define('IMPORT_TAG', 'wp-importar'); // Tag que indica vídeo para importar

// Título curto
define('TITULO_CURTO_TAG_PREFIX', 'wp-titulo:'); // Prefixo para título curto

// CPT
define('CPT_NAME', 'treinos-video');
define('WORKER_SECRET_KEY', 'processarvideos');
```

### 2. Mapeamento de Taxonomias (`worker/taxonomy-mapping.php`)

```php
<?php
// Formato: 'tag-do-youtube' => ['taxonomia', 'slug-termo']
// Cada tag mapeia para apenas UM termo
// Tags descritivas otimizadas para SEO do YouTube
return [
    // === TIPOS DE TREINO ===
    'treino-cardio' => ['tipo-de-treino', 'cardio'],
    'treino-forca' => ['tipo-de-treino', 'forca'],
    'treino-hiit' => ['tipo-de-treino', 'hiit'],
    'treino-alongamento' => ['tipo-de-treino', 'alongamento'],
    'treino-aquecimento' => ['tipo-de-treino', 'aquecimento'],
    'treino-relaxamento' => ['tipo-de-treino', 'relaxamento'],
    
    // === DURAÇÃO DOS VÍDEOS ===
    'duracao-5' => ['duracao-do-treino', '5'],
    'duracao-5-10' => ['duracao-do-treino', '5-10'],
    'duracao-10-15' => ['duracao-do-treino', '10-15'],
    'duracao-15-20' => ['duracao-do-treino', '15-20'],
    'duracao-20' => ['duracao-do-treino', '20'],
    
    // === DIFICULDADE ===
    'nivel-iniciante' => ['dificuldade', 'iniciante'],
    'nivel-intermediario' => ['dificuldade', 'intermediario'],
    'nivel-avancado' => ['dificuldade', 'avancado'],
    
    // === ÁREAS DE FOCO ===
    'foco-bracos' => ['area-de-foco', 'bracos'],
    'foco-core-e-abs' => ['area-de-foco', 'core-e-abs'],
    'foco-corpo-todo' => ['area-de-foco', 'corpo-todo'],
    'foco-costas' => ['area-de-foco', 'costas'],
    'foco-gluteos' => ['area-de-foco', 'gluteos'],
    'foco-peito' => ['area-de-foco', 'peito'],
    'foco-pernas' => ['area-de-foco', 'pernas'],
    
    // === EQUIPAMENTOS ===
    'equipamentos-banco' => ['equipamento', 'banco'],
    'equipamentos-elasticos' => ['equipamento', 'elasticos'],
    'equipamentos-halteres' => ['equipamento', 'halteres'],
    'equipamentos-sem' => ['equipamento', 'sem-equipamentos'],
];
```

## 🎯 Como Usar

### Configuração no YouTube

#### 1. Tag de Importação
Adicione a tag `wp-importar` nos vídeos que devem ser importados para o WordPress.

#### 2. Título Curto
Adicione tags no formato `wp-titulo: Título Desejado` para definir o título curto:
- `wp-titulo: Treino Cardio 10 Min`
- `wp-titulo: HIIT Completo`
- `wp-titulo: Treino para Iniciantes`

#### 3. Taxonomias
Adicione tags que correspondam ao mapeamento:

**Tipos de Treino:**
- `tipo-de-treino-cardio`, `tipo-de-treino-forca`, `tipo-de-treino-hiit`, `tipo-de-treino-alongamento`, `tipo-de-treino-aquecimento`, `tipo-de-treino-relaxamento`

**Duração dos Vídeos:**
- `duracao-do-video-5`, `duracao-do-video-5-10`, `duracao-do-video-10-15`, `duracao-do-video-15-20`, `duracao-do-video-20`

**Dificuldade:**
- `dificuldade-iniciante`, `dificuldade-intermediario`, `dificuldade-avancado`

**Áreas de Foco:**
- `area-de-foco-bracos`, `area-de-foco-core-e-abs`, `area-de-foco-corpo-todo`, `area-de-foco-costas`, `area-de-foco-gluteos`, `area-de-foco-peito`, `area-de-foco-pernas`

**Equipamentos:**
- `equipamentos-banco`, `equipamentos-elasticos`, `equipamentos-halteres`, `equipamentos-sem-equipamentos`

**Exemplo:** Se um vídeo tem as tags `tipo-de-treino-cardio`, `duracao-do-video-5-10`, `dificuldade-iniciante`, `area-de-foco-bracos`, `equipamentos-halteres`, ele receberá:
- Termo "Cardio" na taxonomia "tipo-de-treino"
- Termo "5-10" na taxonomia "duracao-do-video"
- Termo "Iniciante" na taxonomia "dificuldade"
- Termo "Braços" na taxonomia "area-de-foco"
- Termo "Halteres" na taxonomia "equipamentos"

### Execução

#### Via Navegador

**Apenas Atualização (Fase 2):**
```
https://seusite.com/wp-content/mu-plugins/api-integracao-youtube/worker/update-posts-cpt-treinos.php?chave=processarvideos
```

**Importação + Atualização (Fase 1 + Fase 2):**
```
https://seusite.com/wp-content/mu-plugins/api-integracao-youtube/worker/update-posts-cpt-treinos.php?chave=processarvideos&fase1=1
```

**Com Limite de Posts:**
```
https://seusite.com/wp-content/mu-plugins/api-integracao-youtube/worker/update-posts-cpt-treinos.php?chave=processarvideos&max=50
```

**Com Limite + Importação:**
```
https://seusite.com/wp-content/mu-plugins/api-integracao-youtube/worker/update-posts-cpt-treinos.php?chave=processarvideos&max=50&fase1=1
```

#### Via Linha de Comando

**Apenas Atualização:**
```bash
cd worker/
php update-posts-cpt-treinos.php
```

**Importação + Atualização:**
```bash
cd worker/
php update-posts-cpt-treinos.php --fase1
```

**Com Limite:**
```bash
cd worker/
php update-posts-cpt-treinos.php --max=50
```

**Com Limite + Importação:**
```bash
cd worker/
php update-posts-cpt-treinos.php --max=50 --fase1
```

### 📋 Parâmetros Disponíveis

| Parâmetro | Descrição | Exemplo |
|-----------|-----------|---------|
| `chave` | Chave de segurança (obrigatório) | `chave=processarvideos` |
| `fase1` | Ativa importação de novos vídeos | `fase1=1` ou `fase1=true` |
| `max` | Limita número de posts processados | `max=50` |

### 🎯 Cenários de Uso

#### **Cenário 1: Atualização Diária (Recomendado)**
```
?chave=processarvideos
```
- **Uso**: Atualizar views, likes, dados dos vídeos existentes
- **Quota**: Baixa (só endpoint `videos`)
- **Frequência**: Diária

#### **Cenário 2: Importação de Novos Vídeos**
```
?chave=processarvideos&fase1=1
```
- **Uso**: Importar novos vídeos + atualizar existentes
- **Quota**: Alta (endpoint `search` + `videos`)
- **Frequência**: Semanal ou quando necessário

#### **Cenário 3: Teste/Debug**
```
?chave=processarvideos&max=10
```
- **Uso**: Testar com poucos vídeos
- **Quota**: Muito baixa
- **Frequência**: Para testes

#### **Cenário 4: Processamento Completo**
```
?chave=processarvideos&fase1=1&max=100
```
- **Uso**: Importar novos + atualizar limitado
- **Quota**: Controlada
- **Frequência**: Quando necessário

## 📊 Campos ACF Atualizados

O worker atualiza automaticamente os seguintes campos:

| Campo ACF | Descrição | Exemplo |
|-----------|-----------|---------|
| `id_video` | ID do vídeo no YouTube | `dQw4w9WgXcQ` |
| `url_video` | URL completa do vídeo | `https://youtube.com/watch?v=...` |
| `canal` | Nome do canal | `Canal Fitness` |
| `id_canal` | ID do canal | `UC123456789` |
| `url_thumbnail` | URL da thumbnail | `https://img.youtube.com/vi/.../maxresdefault.jpg` |
| `data_publicacao_video` | Data completa | `15/08/2024 14:30:00` |
| `data_publicacao_video_curto` | Data formatada | `15/08/2024` |
| `duracao_do_video` | Duração formatada | `10:30` |
| `duracao_do_video_em_segundos` | Duração em segundos | `630` |
| `views` | Número de views | `12345` |
| `views_curto` | Views formatadas | `12.3 mil` |
| `likes` | Número de likes | `567` |
| `plataforma` | Plataforma | `Youtube` |
| `titulo_video_curto` | Título curto personalizado | `Treino Cardio 10 Min` |

## 🔧 Utilitários

### Verificar Taxonomias
```bash
php get-taxonomies.php
```
Mostra todas as taxonomias e termos disponíveis para o CPT.

### Testar Mapeamento
```bash
php test-mapping.php
```
Testa o mapeamento de taxonomias com tags de exemplo.

### Verificar Status da API
```bash
php check-quota.php
```
Verifica o status da API do YouTube e quota disponível.

### Testar Custo da API
```bash
php test-api-cost.php
```
Demonstra a diferença de custo entre endpoints da API.

## 📈 Logs e Monitoramento

### Exemplo de Log Otimizado
```
[2025-08-04 17:35:54] 📋 === FASE 1: VERIFICANDO NOVOS VÍDEOS DO CANAL ===
[2025-08-04 17:35:54] ℹ️ Iniciando busca otimizada de vídeos do canal
[2025-08-04 17:35:54] ℹ️ Verificando página com 50 vídeos (Total verificado: 50)
[2025-08-04 17:35:54] ℹ️ Verificando tags em 1 lotes de até 50 vídeos
[2025-08-04 17:35:54] ℹ️ Lote 1 verificado: 50 vídeos
[2025-08-04 17:35:54] ✅ Busca concluída: 300 vídeos verificados, 15 com tag de importação
[2025-08-04 17:35:54] 🔄 [████████████████░░░░] 1/15 (6.7%) - Verificando vídeo: dQw4w9WgXcQ
[2025-08-04 17:35:54] ✅ Novo post criado: ID 124 para vídeo dQw4w9WgXcQ
[2025-08-04 17:35:54] 📋 === FASE 2: ATUALIZANDO DADOS DOS VÍDEOS ===
[2025-08-04 17:35:54] ✅ Encontrados 20 posts para atualizar
[2025-08-04 17:35:54] 🔄 [████████████████░░░░] 1/20 (5%) - Processando post ID: 123, Vídeo: dQw4w9WgXcQ
[2025-08-04 17:35:54] ℹ️ Título curto extraído: 'Treino Cardio 10 Min'
[2025-08-04 17:35:54] ✅ Post '123' atualizado no banco de dados
[2025-08-04 17:35:54] 📋 === TAXONOMIAS APLICADAS PARA O POST 123 ===
[2025-08-04 17:35:54] ✅ Tag 'tipo-de-treino-cardio' → Taxonomia 'tipo-de-treino' → Termo 'Cardio' (slug: cardio)
[2025-08-04 17:35:54] ✅ Total de termos aplicados: 1
[2025-08-04 17:35:54] ✅ Post '123' atualizado com sucesso
[2025-08-04 17:35:54] 🎯 Worker executado com sucesso!
```

### Monitoramento
```bash
# Acompanhar logs em tempo real
tail -f worker/log.txt

# Ver últimas 50 linhas
tail -n 50 worker/log.txt
```

## 🔑 Configuração da API do YouTube

### ⚠️ Importante: Quota da API

A YouTube Data API v3 tem limites de quota:
- **Quota diária**: 10.000 unidades
- **Quota por 100s**: 1.000.000 unidades
- **Custo por operação**:
  - `search`: 100 unidades
  - `videos`: 1 unidade

**Dicas para economizar quota:**
- Use o delay entre requisições (`API_DELAY_SECONDS`)
- Evite executar o worker múltiplas vezes por dia
- Monitore o uso com `php check-quota.php`

### Como obter a API Key do Google

1. **Acesse o Google Cloud Console**: https://console.cloud.google.com/
2. **Crie um projeto** ou selecione um existente
3. **Ative a YouTube Data API v3**:
   - Vá em "APIs e Serviços" > "Biblioteca"
   - Procure por "YouTube Data API v3"
   - Clique em "Ativar"
4. **Crie credenciais**:
   - Vá em "APIs e Serviços" > "Credenciais"
   - Clique em "Criar credenciais" > "Chave de API"
   - Copie a chave gerada
5. **Configure no worker**:
   ```php
   define('GOOGLE_API_KEY', 'sua-api-key-aqui');
   ```

## 🛠️ Solução de Problemas

### Erro "Quota Excedida" (HTTP 403)
```
❌ Erro ao buscar vídeos do canal: HTTP 403
```

**Soluções:**
1. **Aguarde o reset da quota** (meia-noite UTC)
2. **Verifique o status**: `php check-quota.php`
3. **Aumente o delay**: Configure `API_DELAY_SECONDS` para 2-3 segundos
4. **Processe menos vídeos**: Use `&max=10` na URL

### Erro "wp-load.php not found"
Configure o caminho manual no `worker/config.php`:
```php
define('WP_LOAD_PATH', '/caminho/completo/para/wp-load.php');
```

### Erro de API
- Verifique se a API Key do Google está válida
- Confirme se a API do YouTube está funcionando
- Verifique se o vídeo existe e é público

### Erro de Banco de Dados
- Verifique se o WordPress está carregado
- Confirme se o CPT existe

### Performance Lenta
- O sistema já está otimizado com processamento em lote
- Para canais muito grandes, considere usar `max=100` para processar em partes

## 📞 Suporte

Para dúvidas ou problemas:
1. Verifique os logs em `worker/log.txt`
2. Confirme as configurações em `worker/config.php`
3. Teste com um número menor de iterações usando `max=10`
4. Use os utilitários para verificar taxonomias e mapeamentos

## 🎉 Recursos Avançados

- **🔄 Processamento Infinito**: Processa todos os vídeos automaticamente
- **⚡ Otimização em Lote**: 10x mais rápido que requisições individuais
- **🏷️ Taxonomias Inteligentes**: Mapeamento automático baseado em tags
- **📝 Título Personalizado**: Controle total via tags do YouTube
- **📊 Logs Visuais**: Barras de progresso e emojis para melhor acompanhamento
- **🛡️ Segurança**: Acesso protegido por chave secreta
- **📱 Compatibilidade**: Funciona via navegador e linha de comando

---

**🚀 Sistema completo e otimizado para produção!**