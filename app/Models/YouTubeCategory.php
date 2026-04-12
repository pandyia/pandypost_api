<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class YouTubeCategory extends Model
{
    protected $table = 'youtube_categories';

    // Nós desativamos o auto-increment porque os IDs (ex: 22, 20) são os IDs REAIS oficiais da API do YouTube.
    // Injetamos eles na mão no Seeder para que o nosso banco seja um espelho perfeito do banco do Google.
    public $incrementing = false;
    protected $fillable = ['id', 'name'];
}
