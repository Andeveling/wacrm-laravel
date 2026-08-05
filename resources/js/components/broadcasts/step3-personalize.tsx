import { ArrowLeft, ArrowRight, Eye } from 'lucide-react';
import { useMemo } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { MessageTemplate } from '@/types';

interface Step3Props {
  template: MessageTemplate;
  variables: Record<string, string>;
  onUpdate: (variables: Record<string, string>) => void;
  onNext: () => void;
  onBack: () => void;
}

function extractPlaceholders(bodyText: string): string[] {
  const matches = bodyText.match(/\{\{(\d+)\}\}/g) ?? [];
  return Array.from(new Set(matches.map((m) => m.replace(/[{}]/g, ''))));
}

export function Step3Personalize({
  template,
  variables,
  onUpdate,
  onNext,
  onBack,
}: Step3Props) {
  const placeholders = useMemo(
    () => extractPlaceholders(template.body_text),
    [template.body_text],
  );

  const preview = placeholders.reduce(
    (body, key) =>
      body.replace(`{{${key}}}`, variables[key]?.trim() || `{{${key}}}`),
    template.body_text,
  );

  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-foreground">
          Personaliza el mensaje
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Completa las variables de la plantilla.
        </p>
      </div>

      {placeholders.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          Esta plantilla no tiene variables.
        </p>
      ) : (
        <div className="space-y-3">
          {placeholders.map((key) => (
            <div key={key} className="space-y-1.5">
              <Label>Variable {key}</Label>
              <Input
                value={variables[key] ?? ''}
                onChange={(e) =>
                  onUpdate({ ...variables, [key]: e.target.value })
                }
                placeholder={`Valor para {{${key}}}`}
              />
            </div>
          ))}
        </div>
      )}

      <div className="rounded-xl border border-border bg-card/50 p-4">
        <p className="mb-2 flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
          <Eye className="h-3.5 w-3.5" />
          Vista previa
        </p>
        <p className="rounded-lg bg-muted p-3 text-sm whitespace-pre-wrap text-foreground">
          {preview}
        </p>
      </div>

      <div className="flex items-center justify-between border-t border-border pt-4">
        <Button variant="outline" onClick={onBack}>
          <ArrowLeft className="h-4 w-4" />
          Atrás
        </Button>
        <Button onClick={onNext}>
          Siguiente
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
