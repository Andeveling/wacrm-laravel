import { Head } from '@inertiajs/react';
import { AlertCircle, Plus, RefreshCw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { MOCK_TEMPLATES } from '@/lib/broadcasts/mock';
import { templateStatusConfig } from '@/lib/template-status';
import type { MessageTemplate, MessageTemplateCategory } from '@/types';

const CATEGORIES: MessageTemplateCategory[] = [
  'Marketing',
  'Utility',
  'Authentication',
];
const LANGUAGES = ['es_CO', 'es_ES', 'es_MX', 'en_US', 'en_GB', 'pt_BR'];
const NAME_REGEX = /^[a-z0-9_]{1,512}$/;
const BODY_MAX_LENGTH = 1024;
const FOOTER_MAX_LENGTH = 60;

const CATEGORY_COLORS: Record<string, string> = {
  Marketing: 'bg-purple-600/20 text-purple-400 border-purple-600/30',
  Utility: 'bg-blue-600/20 text-blue-400 border-blue-600/30',
  Authentication: 'bg-amber-600/20 text-amber-400 border-amber-600/30',
};

function mockTemplates(): MessageTemplate[] {
  return [
    ...MOCK_TEMPLATES,
    {
      id: 'tpl-encuesta',
      name: 'encuesta_satisfaccion',
      category: 'Utility',
      language: 'es_CO',
      body_text: 'Hola {{1}}, ¿cómo calificarías tu experiencia con nosotros?',
      status: 'PENDING',
    },
    {
      id: 'tpl-descuento',
      name: 'descuento_black_friday',
      category: 'Marketing',
      language: 'es_CO',
      body_text: '¡{{1}}, tenemos un descuento especial solo para ti!',
      status: 'REJECTED',
      rejection_reason:
        'El contenido promocional no cumple con las políticas de Meta.',
    },
  ];
}

interface TemplateFormData {
  name: string;
  category: MessageTemplateCategory;
  language: string;
  body_text: string;
  footer_text: string;
}

function emptyForm(): TemplateFormData {
  return {
    name: '',
    category: 'Marketing',
    language: 'es_CO',
    body_text: '',
    footer_text: '',
  };
}

export default function Templates() {
  const [templates, setTemplates] = useState<MessageTemplate[]>(() =>
    mockTemplates(),
  );
  const [syncing, setSyncing] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editingId, setEditingId] = useState<string | null>(null);
  const [form, setForm] = useState<TemplateFormData>(emptyForm());
  const [templateToDelete, setTemplateToDelete] =
    useState<MessageTemplate | null>(null);

  function openCreate() {
    setEditingId(null);
    setForm(emptyForm());
    setDialogOpen(true);
  }

  function openEdit(template: MessageTemplate) {
    setEditingId(template.id);
    setForm({
      name: template.name,
      category: template.category,
      language: template.language ?? 'es_CO',
      body_text: template.body_text,
      footer_text: template.footer_text ?? '',
    });
    setDialogOpen(true);
  }

  function handleSyncFromMeta() {
    setSyncing(true);
    setTimeout(() => {
      setSyncing(false);
      toast.success('Plantillas sincronizadas con Meta.');
    }, 800);
  }

  function handleSave() {
    const name = form.name.trim().toLowerCase();
    if (!editingId && !NAME_REGEX.test(name)) {
      toast.error(
        'El nombre debe usar solo minúsculas, dígitos y guiones bajos.',
      );
      return;
    }
    if (!form.body_text.trim()) {
      toast.error('El cuerpo del mensaje es obligatorio.');
      return;
    }

    if (editingId) {
      setTemplates((prev) =>
        prev.map((t) =>
          t.id === editingId
            ? {
                ...t,
                category: form.category,
                language: form.language,
                body_text: form.body_text,
                footer_text: form.footer_text || undefined,
                status: 'PENDING',
              }
            : t,
        ),
      );
      toast.success('Plantilla reenviada para revisión.');
    } else {
      setTemplates((prev) => [
        ...prev,
        {
          id: `tpl-${Date.now()}`,
          name,
          category: form.category,
          language: form.language,
          body_text: form.body_text,
          footer_text: form.footer_text || undefined,
          status: 'PENDING',
        },
      ]);
      toast.success('Plantilla enviada a Meta para aprobación.');
    }
    setDialogOpen(false);
  }

  function handleDelete() {
    if (!templateToDelete) return;
    setTemplates((prev) => prev.filter((t) => t.id !== templateToDelete.id));
    toast.success('Plantilla eliminada.');
    setTemplateToDelete(null);
  }

  return (
    <>
      <Head title="Plantillas" />

      <div className="space-y-4">
        <div className="flex flex-wrap items-start justify-between gap-3">
          <Heading
            title="Plantillas"
            description="Plantillas de mensajes aprobadas por Meta."
          />
          <div className="flex items-center gap-2">
            <Button
              variant="outline"
              onClick={handleSyncFromMeta}
              disabled={syncing}
            >
              <RefreshCw
                className={`size-4 ${syncing ? 'animate-spin' : ''}`}
              />
              {syncing ? 'Sincronizando…' : 'Sincronizar con Meta'}
            </Button>
            <Button onClick={openCreate}>
              <Plus className="size-4" />
              Nueva plantilla
            </Button>
          </div>
        </div>

        {templates.length === 0 ? (
          <Card>
            <CardContent className="flex flex-col items-center justify-center py-12 text-center">
              <p className="text-sm text-muted-foreground">
                Sin plantillas todavía.
              </p>
              <p className="mt-1 text-xs text-muted-foreground">
                Crea la primera para empezar a enviar difusiones.
              </p>
            </CardContent>
          </Card>
        ) : (
          <div className="grid gap-3 xl:grid-cols-2">
            {templates.map((template) => {
              const status = templateStatusConfig[template.status ?? 'DRAFT'];
              return (
                <Card key={template.id}>
                  <CardContent className="flex items-start justify-between gap-2 pt-4">
                    <div className="min-w-0 flex-1 space-y-2">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-medium text-foreground">
                          {template.name}
                        </h3>
                        <Badge
                          className={`border text-xs ${CATEGORY_COLORS[template.category] ?? ''}`}
                        >
                          {template.category}
                        </Badge>
                        <Badge className={`border text-xs ${status.classes}`}>
                          {status.label}
                        </Badge>
                        {template.language && (
                          <span className="text-xs text-muted-foreground uppercase">
                            {template.language}
                          </span>
                        )}
                      </div>
                      <p className="line-clamp-2 text-sm text-muted-foreground">
                        {template.body_text}
                      </p>
                      {template.footer_text && (
                        <p className="text-xs text-muted-foreground italic">
                          {template.footer_text}
                        </p>
                      )}
                      {template.rejection_reason && (
                        <div className="flex items-start gap-1.5 rounded border border-destructive/40 bg-destructive/10 px-2 py-1.5 text-xs text-destructive">
                          <AlertCircle className="mt-0.5 size-3.5 shrink-0" />
                          <span>{template.rejection_reason}</span>
                        </div>
                      )}
                    </div>
                    <div className="flex shrink-0 items-center gap-1">
                      <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => openEdit(template)}
                        className="h-8 px-2"
                      >
                        Editar
                      </Button>
                      <Button
                        variant="ghost"
                        size="icon"
                        onClick={() => setTemplateToDelete(template)}
                        className="size-8 text-muted-foreground hover:text-destructive"
                      >
                        <Trash2 className="size-4" />
                      </Button>
                    </div>
                  </CardContent>
                </Card>
              );
            })}
          </div>
        )}
      </div>

      <Dialog open={dialogOpen} onOpenChange={setDialogOpen}>
        <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
          <DialogHeader>
            <DialogTitle>
              {editingId ? 'Editar plantilla' : 'Nueva plantilla'}
            </DialogTitle>
            <DialogDescription>
              {editingId
                ? 'Los cambios se reenvían a Meta para aprobación.'
                : 'Se enviará a Meta para revisión antes de poder usarse.'}
            </DialogDescription>
          </DialogHeader>

          {form.category === 'Authentication' && (
            <div className="flex items-start gap-2 rounded border border-amber-700/40 bg-amber-950/30 px-3 py-2 text-xs text-amber-300">
              <AlertCircle className="mt-0.5 size-4 shrink-0" />
              <p>
                Las plantillas de autenticación tienen restricciones adicionales
                de contenido en Meta.
              </p>
            </div>
          )}

          <div className="space-y-4 py-2">
            <div className="space-y-2">
              <Label htmlFor="tpl-name">Nombre de la plantilla</Label>
              <Input
                id="tpl-name"
                placeholder="ej. promo_agosto"
                value={form.name}
                onChange={(e) => setForm({ ...form, name: e.target.value })}
                disabled={editingId !== null}
              />
              <p className="text-[11px] text-muted-foreground">
                {editingId
                  ? 'El nombre no se puede cambiar.'
                  : 'Solo minúsculas, dígitos y guiones bajos.'}
              </p>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-2">
                <Label htmlFor="tpl-category">Categoría</Label>
                <Select
                  value={form.category}
                  onValueChange={(val) =>
                    val &&
                    setForm({
                      ...form,
                      category: val as MessageTemplateCategory,
                    })
                  }
                >
                  <SelectTrigger id="tpl-category" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {CATEGORIES.map((cat) => (
                      <SelectItem key={cat} value={cat}>
                        {cat}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-2">
                <Label htmlFor="tpl-language">Idioma</Label>
                <Select
                  value={form.language}
                  onValueChange={(val) =>
                    val && setForm({ ...form, language: val })
                  }
                >
                  <SelectTrigger id="tpl-language" className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    {LANGUAGES.map((lang) => (
                      <SelectItem key={lang} value={lang}>
                        {lang}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="tpl-body">Cuerpo del mensaje</Label>
              <Textarea
                id="tpl-body"
                placeholder="Hola {{1}}, …"
                value={form.body_text}
                onChange={(e) =>
                  setForm({ ...form, body_text: e.target.value })
                }
                maxLength={BODY_MAX_LENGTH}
                className="min-h-28"
              />
              <p className="text-[11px] text-muted-foreground">
                Usa {'{{1}}'}, {'{{2}}'}, … para variables.{' '}
                {form.body_text.length}/{BODY_MAX_LENGTH}
              </p>
            </div>

            <div className="space-y-2">
              <Label htmlFor="tpl-footer">Pie de página (opcional)</Label>
              <Input
                id="tpl-footer"
                placeholder="Texto pequeño al final del mensaje"
                value={form.footer_text}
                onChange={(e) =>
                  setForm({ ...form, footer_text: e.target.value })
                }
                maxLength={FOOTER_MAX_LENGTH}
              />
            </div>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setDialogOpen(false)}>
              Cancelar
            </Button>
            <Button onClick={handleSave}>
              {editingId ? 'Reenviar' : 'Enviar a revisión'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog
        open={!!templateToDelete}
        onOpenChange={(o) => !o && setTemplateToDelete(null)}
      >
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Eliminar plantilla</DialogTitle>
            <DialogDescription>
              {templateToDelete
                ? `¿Eliminar «${templateToDelete.name}»? Esta acción no se puede deshacer.`
                : null}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setTemplateToDelete(null)}>
              Cancelar
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  );
}

Templates.layout = {
  breadcrumbs: [
    { title: 'Settings', href: '/settings' },
    { title: 'Plantillas' },
  ],
};
