import type { ContactsPageProps } from '@/modules/contacts/contracts';
import { ContactsScreen } from '@/modules/contacts/ui/contacts-screen';

export default function ContactsPage(props: ContactsPageProps) {
  return <ContactsScreen {...props} />;
}

ContactsPage.layout = {
  breadcrumbs: [
    {
      title: 'Contactos',
      href: '/contacts',
    },
  ],
};
