import { Building2, Mail, Phone } from 'lucide-react';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Badge } from '@/components/ui/badge';
import type { ConversationContact } from '../types';

function initials(name?: string, fallback?: string) {
  const source = (name || fallback || '?').trim();
  return source.charAt(0).toUpperCase();
}

export function ContactSidebar({ contact }: { contact: ConversationContact }) {
  return (
    <aside className="hidden w-72 shrink-0 border-l border-border p-4 lg:block">
      <div className="flex flex-col items-center gap-2 text-center">
        <Avatar className="size-14">
          <AvatarFallback className="text-lg">
            {initials(contact.name, contact.phone)}
          </AvatarFallback>
        </Avatar>
        <p className="text-sm font-semibold text-foreground">
          {contact.name || 'Sin nombre'}
        </p>
      </div>

      <div className="mt-4 space-y-2 text-sm">
        <div className="flex items-center gap-2 text-muted-foreground">
          <Phone className="size-3.5" />
          {contact.phone}
        </div>
        {contact.email ? (
          <div className="flex items-center gap-2 text-muted-foreground">
            <Mail className="size-3.5" />
            {contact.email}
          </div>
        ) : null}
        {contact.company ? (
          <div className="flex items-center gap-2 text-muted-foreground">
            <Building2 className="size-3.5" />
            {contact.company}
          </div>
        ) : null}
      </div>

      {contact.tags && contact.tags.length > 0 && (
        <div className="mt-4">
          <p className="mb-1.5 text-xs font-medium text-muted-foreground">
            Etiquetas
          </p>
          <div className="flex flex-wrap gap-1">
            {contact.tags.map((tag) => (
              <Badge
                key={tag.id}
                style={{ backgroundColor: `${tag.color}20`, color: tag.color }}
                className="border-transparent"
              >
                {tag.name}
              </Badge>
            ))}
          </div>
        </div>
      )}
    </aside>
  );
}
