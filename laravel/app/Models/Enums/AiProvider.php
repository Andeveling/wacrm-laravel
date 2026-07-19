<?php

namespace App\Models\Enums;

/**
 * Proveedor LLM del asistente IA. Espeja el CHECK de Supabase 029.
 * El runtime (#37) ofrece solo OpenAI de momento; el esquema conserva
 * ambos para paridad con Supabase.
 */
enum AiProvider: string
{
    case OpenAi = 'openai';
    case Anthropic = 'anthropic';
}
