const STATUS_CONFIG = {
    ADMIN: ['Administrator', 'bg-sky-50 text-sky-800 ring-sky-200', 'A'],
    SIGNER: ['Signer', 'bg-indigo-50 text-indigo-800 ring-indigo-200', 'S'],
    PENDING: ['Menunggu', 'bg-zinc-100 text-zinc-700 ring-zinc-200', '-'],
    SIGNED: ['Ditandatangani', 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'OK'],
    WAITING_SIGNATURE: ['Menunggu Tanda Tangan', 'bg-amber-50 text-amber-900 ring-amber-200', '-'],
    PARTIALLY_SIGNED: ['Ditandatangani Sebagian', 'bg-sky-50 text-sky-800 ring-sky-200', '~'],
    COMPLETED: ['Selesai', 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'OK'],
    VALID: ['Valid', 'bg-emerald-50 text-emerald-800 ring-emerald-200', 'OK'],
    INCOMPLETE: ['Belum Lengkap', 'bg-amber-50 text-amber-900 ring-amber-200', '!'],
    INVALID_DOCUMENT_MODIFIED: ['Dokumen Diubah', 'bg-red-50 text-red-800 ring-red-200', 'X'],
    INVALID_SIGNATURE: ['Tanda Tangan Tidak Valid', 'bg-red-50 text-red-800 ring-red-200', 'X'],
    INVALID_PUBLIC_KEY: ['Public Key Tidak Valid', 'bg-red-50 text-red-800 ring-red-200', 'X'],
    REJECTED_TOKEN_NOT_FOUND: ['Token Tidak Ditemukan', 'bg-red-50 text-red-800 ring-red-200', 'X'],
};

export function StatusBadge({ status }) {
    const [label, classes, symbol] = STATUS_CONFIG[status] ?? [status, 'bg-zinc-100 text-zinc-700 ring-zinc-200', '-'];

    return (
        <span className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${classes}`}>
            <span aria-hidden="true">{symbol}</span>
            {label}
        </span>
    );
}

export function Card({ children, className = '' }) {
    return <section className={`ui-card ${className}`}>{children}</section>;
}

export function SectionHeading({ title, description = null, action = null }) {
    return (
        <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <h2 className="text-base font-semibold text-zinc-950">{title}</h2>
                {description && <p className="mt-1 text-sm text-zinc-600">{description}</p>}
            </div>
            {action}
        </div>
    );
}

export function MetaItem({ label, value, mono = false, children = null }) {
    return (
        <div className="min-w-0">
            <dt className="text-xs font-semibold text-zinc-500 uppercase">{label}</dt>
            <dd className={`mt-1 break-words text-sm font-medium text-zinc-900 ${mono ? 'font-mono text-xs' : ''}`}>
                {children ?? value}
            </dd>
        </div>
    );
}

export function FieldError({ children }) {
    if (!children) {
        return null;
    }

    return (
        <span className="flex items-start gap-1.5 text-sm font-normal text-red-700" role="alert">
            <span aria-hidden="true">!</span>
            {children}
        </span>
    );
}

export function Alert({ tone = 'info', children }) {
    const tones = {
        info: 'border-sky-200 bg-sky-50 text-sky-900',
        warning: 'border-amber-200 bg-amber-50 text-amber-950',
        success: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        danger: 'border-red-200 bg-red-50 text-red-900',
    };

    return (
        <div className={`rounded-md border p-4 text-sm ${tones[tone]}`} role={tone === 'danger' ? 'alert' : 'status'}>
            {children}
        </div>
    );
}

export function EmptyState({ title, description = null }) {
    return (
        <div className="px-5 py-12 text-center">
            <p className="font-semibold text-zinc-700">{title}</p>
            {description && <p className="mt-1 text-sm text-zinc-500">{description}</p>}
        </div>
    );
}
