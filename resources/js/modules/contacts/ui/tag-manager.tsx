import { Plus, Tag as TagIcon, X } from 'lucide-react';
import { useState } from 'react';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { Tag } from '../contracts';
import { MOCK_TAGS } from '../fixtures';

const PRESET_COLORS = [
  { name: 'rojo', value: '#ef4444' },
  { name: 'naranja', value: '#f97316' },
  { name: 'ámbar', value: '#f59e0b' },
  { name: 'esmeralda', value: '#10b981' },
  { name: 'cian', value: '#06b6d4' },
  { name: 'azul', value: '#3b82f6' },
  { name: 'violeta', value: '#8b5cf6' },
  { name: 'rosa', value: '#ec4899' },
];

export function TagManager() {
  const [tags, setTags] = useState<Tag[]>(() => [...MOCK_TAGS]);
  const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
  const [tagToDelete, setTagToDelete] = useState<Tag | null>(null);
  const [newTagName, setNewTagName] = useState('');
  const [selectedColor, setSelectedColor] = useState(PRESET_COLORS[3].value);

  function handleCreate() {
    const name = newTagName.trim();
    if (!name) {
      toast.error('El nombre es obligatorio.');
      return;
    }
    setTags((prev) => [
      ...prev,
      { id: `tag-${Date.now()}`, name, color: selectedColor },
    ]);
    toast.success('Etiqueta creada.');
    setNewTagName('');
    setSelectedColor(PRESET_COLORS[3].value);
  }

  function confirmDelete(tag: Tag) {
    setTagToDelete(tag);
    setDeleteDialogOpen(true);
  }

  function handleDelete() {
    if (!tagToDelete) return;
    setTags((prev) => prev.filter((t) => t.id !== tagToDelete.id));
    toast.success('Etiqueta eliminada.');
    setDeleteDialogOpen(false);
    setTagToDelete(null);
  }

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2">
          <TagIcon className="size-4 text-primary" />
          Etiquetas
        </CardTitle>
        <CardDescription>
          Etiquetas de contacto codificadas por color.
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-4">
        {tags.length > 0 ? (
          <div className="flex flex-wrap gap-2">
            {tags.map((tag) => (
              <span
                key={tag.id}
                className="group inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium"
                style={{
                  backgroundColor: `${tag.color}20`,
                  color: tag.color,
                  border: `1px solid ${tag.color}40`,
                }}
              >
                <span
                  className="size-2 rounded-full"
                  style={{ backgroundColor: tag.color }}
                />
                {tag.name}
                <button
                  type="button"
                  onClick={() => confirmDelete(tag)}
                  aria-label={`Eliminar ${tag.name}`}
                  className="ml-0.5 rounded-full p-0.5 opacity-60 transition-opacity hover:bg-black/10 hover:opacity-100 dark:hover:bg-white/10"
                >
                  <X className="size-3" />
                </button>
              </span>
            ))}
          </div>
        ) : (
          <p className="text-sm text-muted-foreground">
            Sin etiquetas todavía.
          </p>
        )}

        <div className="flex flex-wrap items-center gap-2.5">
          <Input
            placeholder="Nombre de la etiqueta"
            value={newTagName}
            onChange={(e) => setNewTagName(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') handleCreate();
            }}
            maxLength={40}
            className="min-w-[180px] flex-1"
          />
          <div className="flex gap-1.5">
            {PRESET_COLORS.map((color) => (
              <button
                key={color.value}
                type="button"
                onClick={() => setSelectedColor(color.value)}
                aria-label={`Usar color ${color.name}`}
                aria-pressed={selectedColor === color.value}
                className={cn(
                  'size-6 rounded-md transition-transform hover:scale-110',
                  selectedColor === color.value &&
                    'outline outline-2 outline-offset-2 outline-primary',
                )}
                style={{ backgroundColor: color.value }}
                title={color.name}
              />
            ))}
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={handleCreate}
            disabled={!newTagName.trim()}
          >
            <Plus className="size-4" />
            Agregar etiqueta
          </Button>
        </div>
      </CardContent>

      <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
        <DialogContent className="sm:max-w-sm">
          <DialogHeader>
            <DialogTitle>Eliminar etiqueta</DialogTitle>
            <DialogDescription>
              {tagToDelete
                ? `¿Eliminar «${tagToDelete.name}»? Se quitará de todos los contactos.`
                : null}
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="ghost" onClick={() => setDeleteDialogOpen(false)}>
              Cancelar
            </Button>
            <Button variant="destructive" onClick={handleDelete}>
              Eliminar
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  );
}
