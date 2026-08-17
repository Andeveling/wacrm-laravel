import { ArrowLeft, ArrowRight, Tags, Users } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { BroadcastTag } from '../types';

export type AudienceType = 'all' | 'tags';

export interface AudienceConfig {
  type: AudienceType;
  tagIds?: string[];
}

interface Step2Props {
  tags: BroadcastTag[];
  audienceCount: number | null;
  audience: AudienceConfig;
  onUpdate: (audience: AudienceConfig) => void;
  onNext: () => void;
  onBack: () => void;
}

const AUDIENCE_OPTIONS: {
  type: AudienceType;
  label: string;
  description: string;
  icon: typeof Users;
}[] = [
  {
    type: 'all',
    label: 'Todos los contactos',
    description: 'Envía a toda tu lista de contactos.',
    icon: Users,
  },
  {
    type: 'tags',
    label: 'Por etiqueta',
    description: 'Envía solo a contactos con las etiquetas seleccionadas.',
    icon: Tags,
  },
];

export function Step2SelectAudience({
  tags,
  audienceCount,
  audience,
  onUpdate,
  onNext,
  onBack,
}: Step2Props) {
  const canProceed = audienceCount !== null && audienceCount > 0;

  function toggleTag(tagId: string) {
    const current = audience.tagIds ?? [];
    const next = current.includes(tagId)
      ? current.filter((id) => id !== tagId)
      : [...current, tagId];
    onUpdate({ ...audience, tagIds: next });
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-foreground">
          Selecciona la audiencia
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          ¿A quién quieres enviar esta difusión?
        </p>
      </div>

      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
        {AUDIENCE_OPTIONS.map((option) => {
          const Icon = option.icon;
          const isSelected = audience.type === option.type;
          return (
            <button
              type="button"
              key={option.type}
              onClick={() =>
                onUpdate({
                  type: option.type,
                  tagIds:
                    option.type === 'tags'
                      ? (audience.tagIds ?? [])
                      : undefined,
                })
              }
              className={`flex flex-col gap-2 rounded-xl border p-4 text-left transition-all ${
                isSelected
                  ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                  : 'border-border bg-card/50 hover:border-border hover:bg-card'
              }`}
            >
              <Icon className="h-5 w-5 text-primary" />
              <span className="text-sm font-medium text-foreground">
                {option.label}
              </span>
              <span className="text-xs text-muted-foreground">
                {option.description}
              </span>
            </button>
          );
        })}
      </div>

      {audience.type === 'tags' && (
        <div className="rounded-xl border border-border bg-card/50 p-4">
          <p className="mb-2 text-sm font-medium text-foreground">Etiquetas</p>
          <div className="flex flex-wrap gap-1.5">
            {tags.map((tag) => {
              const selected = (audience.tagIds ?? []).includes(tag.id);
              return (
                <button
                  key={tag.id}
                  type="button"
                  onClick={() => toggleTag(tag.id)}
                  className={`cursor-pointer rounded-full px-2.5 py-0.5 text-xs font-medium transition-opacity ${selected ? 'ring-2 ring-primary ring-offset-1' : 'opacity-60 hover:opacity-100'}`}
                  style={{
                    backgroundColor: `${tag.color}20`,
                    color: tag.color,
                  }}
                >
                  {tag.name}
                </button>
              );
            })}
          </div>
        </div>
      )}

      <p className="text-sm text-muted-foreground" aria-live="polite">
        {audienceCount === null
          ? 'Calculando contactos alcanzados…'
          : audienceCount === 0
            ? 'Esta audiencia no tiene contactos. Selecciona otra para continuar.'
            : `${audienceCount.toLocaleString()} contactos alcanzados.`}
      </p>

      <div className="flex items-center justify-between border-t border-border pt-4">
        <Button variant="outline" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" />
          Atrás
        </Button>
        <Button onClick={onNext} disabled={!canProceed}>
          Siguiente
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
