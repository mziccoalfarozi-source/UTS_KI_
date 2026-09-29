import { Link } from '@inertiajs/react';

export default function AppShell({ title, children, actions = null, maxWidth = 'max-w-6xl' }) {
    return (
        <main className="min-h-screen bg-zinc-100">
            <header className="border-b border-zinc-200 bg-white">
                <div className={`mx-auto flex ${maxWidth} flex-wrap items-center justify-between gap-4 px-4 py-4 sm:px-6`}>
                    <div className="min-w-0">
                        <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                        <h1 className="truncate text-lg font-semibold text-zinc-950">{title}</h1>
                    </div>
                    {actions && <nav className="flex flex-wrap items-center gap-2">{actions}</nav>}
                </div>
            </header>
            <div className={`mx-auto ${maxWidth} px-4 py-6 sm:px-6 sm:py-8`}>{children}</div>
        </main>
    );
}

export function BackLink({ href, children = 'Kembali' }) {
    return (
        <Link href={href} className="ui-button-secondary">
            <span aria-hidden="true">&larr;</span>
            <span className="ml-2">{children}</span>
        </Link>
    );
}
