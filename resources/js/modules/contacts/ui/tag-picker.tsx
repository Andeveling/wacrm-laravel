import { cn } from '@/lib/utils';
import type { Tag } from '../contracts';

interface TagPickerProps {
  tags: Tag[];
  selectedIds: string[];
  onChange: (tagIds: string[]) => void;
}

export function TagPicker({ tags, selectedIds, onChange }: TagPickerProps) {
  function toggle(tagId: string) {
    onChange(
      selectedIds.includes(tagId)
        ? selectedIds.filter((id) => id !== tagId)
        : [...selectedIds, tagId],
    );
  }

  return (
    <div className="flex flex-wrap gap-1.5">
      {tags.map((tag) => {
        const selected = selectedIds.includes(tag.id);

        return (
          <button
            key={tag.id}
            type="button"
            aria-pressed={selected}
            onClick={() => toggle(tag.id)}
            className={cn(
              'cursor-pointer rounded-full px-2.5 py-0.5 text-xs font-medium transition-opacity',
              selected
                ? 'ring-2 ring-primary ring-offset-1'
                : 'opacity-60 hover:opacity-100',
            )}
            style={{ backgroundColor: `${tag.color}20`, color: tag.color }}
          >
            {tag.name}
          </button>
        );
      })}
    </div>
  );
}
