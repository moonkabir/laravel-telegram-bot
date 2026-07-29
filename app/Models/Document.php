<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'file_path',
        'file_type',
        'file_size',
        'extracted_text',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'file_size' => 'integer',
    ];

    /**
     * Get the chunks for this document
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class);
    }

    /**
     * Scope for searching documents
     */
    public function scopeSearch($query, $searchTerm)
    {
        return $query->where('name', 'LIKE', "%{$searchTerm}%")
            ->orWhere('extracted_text', 'LIKE', "%{$searchTerm}%");
    }

    /**
     * Get file type icon
     */
    public function getFileIconAttribute(): string
    {
        $icons = [
            'pdf' => 'fa-file-pdf text-red-500',
            'doc' => 'fa-file-word text-blue-500',
            'docx' => 'fa-file-word text-blue-500',
            'jpg' => 'fa-file-image text-green-500',
            'jpeg' => 'fa-file-image text-green-500',
            'png' => 'fa-file-image text-green-500',
            'gif' => 'fa-file-image text-green-500',
            'bmp' => 'fa-file-image text-green-500',
            'txt' => 'fa-file-lines text-gray-500',
            'text' => 'fa-file-lines text-gray-500',
            'xls' => 'fa-file-excel text-green-600',
            'xlsx' => 'fa-file-excel text-green-600',
            'ppt' => 'fa-file-powerpoint text-orange-500',
            'pptx' => 'fa-file-powerpoint text-orange-500',
        ];

        $type = strtolower($this->file_type);

        return $icons[$type] ?? 'fa-file text-gray-400';
    }

    /**
     * Get formatted file size
     */
    public function getFormattedSizeAttribute(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
