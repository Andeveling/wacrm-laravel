import { Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { templates as messageTemplates } from '@/routes/settings';
import type { MessageTemplate } from '../types';

const CATEGORY_COLORS: Record<string, string> = {
  Marketing: 'bg-purple-500/10 text-purple-400 border-purple-500/20',
  Utility: 'bg-blue-500/10 text-blue-400 border-blue-500/20',
  Authentication: 'bg-orange-500/10 text-orange-400 border-orange-500/20',
};

interface Step1Props {
  templates: MessageTemplate[];
  selectedTemplate: MessageTemplate | null;
  onSelect: (template: MessageTemplate) => void;
  onNext: () => void;
  onBack: () => void;
}

export function Step1ChooseTemplate({
  templates,
  selectedTemplate,
  onSelect,
  onNext,
  onBack,
}: Step1Props) {
  return (
    <div className="space-y-6">
      <div>
        <h2 className="text-lg font-semibold text-foreground">
          Elige una plantilla
        </h2>
        <p className="mt-1 text-sm text-muted-foreground">
          Solo se muestran plantillas aprobadas por Meta.
        </p>
      </div>

      {templates.length === 0 ? (
        <div className="rounded-xl border border-dashed border-border bg-card/50 p-6 text-center">
          <p className="font-medium text-foreground">
            No tienes plantillas aprobadas
          </p>
          <p className="mt-1 text-sm text-muted-foreground">
            Crea y aprueba una plantilla en Meta antes de crear una difusión.
          </p>
          <Button asChild className="mt-4">
            <Link href={messageTemplates()}>Ir a plantillas</Link>
          </Button>
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {templates.map((template) => {
            const isSelected = selectedTemplate?.id === template.id;
            const catColor =
              CATEGORY_COLORS[template.category] ?? CATEGORY_COLORS.Utility;
            return (
              <button
                type="button"
                key={template.id}
                onClick={() => onSelect(template)}
                className={`flex flex-col gap-3 rounded-xl border p-4 text-left transition-all ${
                  isSelected
                    ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                    : 'border-border bg-card/50 hover:border-border hover:bg-card'
                }`}
              >
                <div className="flex items-start justify-between">
                  <h3 className="text-sm font-medium text-foreground">
                    {template.name}
                  </h3>
                  <span
                    className={`inline-flex items-center rounded-full border px-2 py-0.5 text-[10px] font-medium ${catColor}`}
                  >
                    {template.category}
                  </span>
                </div>
                <p className="line-clamp-3 text-xs text-muted-foreground">
                  {template.body_text}
                </p>
                <span className="text-[10px] text-muted-foreground">
                  {template.language ?? 'es_CO'}
                </span>
              </button>
            );
          })}
        </div>
      )}

      <div className="flex items-center justify-between border-t border-border pt-4">
        <Button variant="outline" onClick={onBack}>
          Atrás
        </Button>
        <Button
          onClick={onNext}
          disabled={!selectedTemplate || templates.length === 0}
        >
          Siguiente
          <ArrowRight className="h-4 w-4" />
        </Button>
      </div>
    </div>
  );
}
