<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GameSystem extends Model
{
    use HasFactory;
    protected $table = 'game_systems';

    protected $fillable = ['name', 'description', 'user_uuid'];

    public $timestamps = true;
}
