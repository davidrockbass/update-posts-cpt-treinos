<?php
// Mapping de tags do YouTube para taxonomias e termos do WordPress
// Formato: 'tag-do-youtube' => ['taxonomia', 'slug-termo'],
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
    'duracao-5' => ['duracao-do-video', '5'],
    'duracao-5-10' => ['duracao-do-video', '5-10'],
    'duracao-10-15' => ['duracao-do-video', '10-15'],
    'duracao-15-20' => ['duracao-do-video', '15-20'],
    'duracao-20' => ['duracao-do-video', '20'],
    
    // === DIFICULDADE ===
    'dificuldade-iniciante' => ['dificuldade', 'iniciante'],
    'dificuldade-intermediario' => ['dificuldade', 'intermediario'],
    'dificuldade-avancado' => ['dificuldade', 'avancado'],
    
    // === ÁREAS DE FOCO ===
    'foco-bracos' => ['area-de-foco', 'bracos'],
    'foco-core-e-abs' => ['area-de-foco', 'core-e-abs'],
    'foco-corpo-todo' => ['area-de-foco', 'corpo-todo'],
    'foco-costas' => ['area-de-foco', 'costas'],
    'foco-gluteos' => ['area-de-foco', 'gluteos'],
    'foco-peito' => ['area-de-foco', 'peito'],
    'foco-pernas' => ['area-de-foco', 'pernas'],
    
    // === EQUIPAMENTOS ===
    'equipamentos-banco' => ['equipamentos', 'banco'],
    'equipamentos-elasticos' => ['equipamentos', 'elasticos'],
    'equipamentos-halteres' => ['equipamentos', 'halteres'],
    'equipamentos-sem' => ['equipamentos', 'sem-equipamentos'],
]; 