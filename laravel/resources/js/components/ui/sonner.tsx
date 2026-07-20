import { Toaster as Sonner, type ToasterProps } from 'sonner';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            closeButton
            style={
                {
                    '--normal-bg': 'var(--popover)',
                    '--normal-text': 'var(--popover-foreground)',
                    '--normal-border': 'var(--border)',
                    // Pin the close button to the top-right corner.
                    // Sonner defaults to top-left in LTR; flip the three
                    // positioning variables it exposes for that.
                    '--toast-close-button-start': 'unset',
                    '--toast-close-button-end': '0',
                    '--toast-close-button-transform':
                        'translate(35%, -35%)',
                } as React.CSSProperties
            }
            {...props}
        />
    );
}

export { Toaster };
