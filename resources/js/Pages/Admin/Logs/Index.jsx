import { Head, Link, router } from '@inertiajs/react';

const METHOD_LABELS = {
    TOKEN: 'Token',
    UPLOAD: 'Upload PDF',
    UPLOAD_CUSTOM_KEY: 'Upload + Custom Key',
};

const RESULT_STYLES = {
    VALID: 'bg-emerald-100 text-emerald-800',
    INCOMPLETE: 'bg-amber-100 text-amber-800',
};

function resultStyle(code) {
    return RESULT_STYLES[code] ?? 'bg-red-100 text-red-800';
}

function resultLabel(code) {
    const labels = {
        VALID: 'Valid',
        INCOMPLETE: 'Belum Lengkap',
        INVALID_DOCUMENT_MODIFIED: 'Dokumen Diubah',
        INVALID_SIGNATURE: 'Tanda Tangan Tidak Valid',
        INVALID_PUBLIC_KEY: 'Public Key Tidak Valid',
        REJECTED_TOKEN_NOT_FOUND: 'Token Tidak Ditemukan',
    };
    return labels[code] ?? code;
}

export default function LogIndex({ logs }) {
    function prevPage() {
        if (logs.current_page > 1) {
            router.visit(`/admin/logs?page=${logs.current_page - 1}`, { preserveScroll: true });
        }
    }

    function nextPage() {
        if (logs.current_page < logs.last_page) {
            router.visit(`/admin/logs?page=${logs.current_page + 1}`, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Verification Logs" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Verification Logs</h1>
                        </div>
                        <Link href="/documents" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Kembali
                        </Link>
                    </div>
                </header>

                <div className="mx-auto flex max-w-5xl flex-col gap-6 px-5 py-8">
                    <section>
                        <div className="mb-4 flex items-center justify-between gap-4">
                            <h2 className="text-base font-semibold">Log verifikasi</h2>
                            <span className="text-sm text-zinc-500">{logs.total} entri</span>
                        </div>

                        <div className="overflow-x-auto border border-zinc-200 bg-white shadow-sm">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Waktu</th>
                                        <th className="px-4 py-3 font-semibold">Token</th>
                                        <th className="px-4 py-3 font-semibold">Metode</th>
                                        <th className="px-4 py-3 font-semibold">Dokumen</th>
                                        <th className="px-4 py-3 font-semibold">Hasil</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-200">
                                    {logs.data.length === 0 ? (
                                        <tr>
                                            <td className="px-4 py-8 text-center text-zinc-500" colSpan="5">
                                                Belum ada log verifikasi.
                                            </td>
                                        </tr>
                                    ) : (
                                        logs.data.map((log) => (
                                            <tr key={log.id}>
                                                <td className="px-4 py-3 text-zinc-500">
                                                    {new Date(log.created_at).toLocaleString('id-ID')}
                                                </td>
                                                <td className="px-4 py-3 font-mono text-xs text-zinc-700">
                                                    {log.token_prefix}…
                                                </td>
                                                <td className="px-4 py-3">
                                                    {METHOD_LABELS[log.method] ?? log.method}
                                                </td>
                                                <td className="px-4 py-3">
                                                    {log.document_id !== null ? (
                                                        <Link
                                                            href={`/documents/${log.document_id}`}
                                                            className="font-medium text-emerald-700 hover:text-emerald-800"
                                                        >
                                                            {log.document_title ?? log.document_id}
                                                        </Link>
                                                    ) : (
                                                        <span className="text-zinc-400">—</span>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span
                                                        className={`inline-block px-2 py-0.5 text-xs font-semibold ${resultStyle(log.result_code)}`}
                                                    >
                                                        {resultLabel(log.result_code)}
                                                    </span>
                                                </td>
                                            </tr>
                                        ))
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Pagination */}
                        {logs.last_page > 1 && (
                            <div className="mt-4 flex items-center justify-between gap-4">
                                <span className="text-sm text-zinc-500">
                                    Halaman {logs.current_page} dari {logs.last_page}
                                </span>
                                <div className="flex gap-2">
                                    <button
                                        type="button"
                                        onClick={prevPage}
                                        disabled={logs.current_page === 1}
                                        className="h-9 border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        ← Prev
                                    </button>
                                    <button
                                        type="button"
                                        onClick={nextPage}
                                        disabled={logs.current_page === logs.last_page}
                                        className="h-9 border border-zinc-300 bg-white px-4 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        Next →
                                    </button>
                                </div>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </>
    );
}
