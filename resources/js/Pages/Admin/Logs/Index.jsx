import AppShell, { BackLink } from '../../../Components/AppShell';
import { EmptyState, SectionHeading, StatusBadge } from '../../../Components/Ui';
import { Head, Link, router } from '@inertiajs/react';

const METHOD_LABELS = { TOKEN: 'Token', UPLOAD: 'Upload PDF', UPLOAD_CUSTOM_KEY: 'Upload + Custom Key' };

export default function LogIndex({ logs }) {
    function visitPage(page) {
        router.visit(`/admin/logs?page=${page}`, { preserveScroll: true });
    }

    return (
        <>
            <Head title="Log Verifikasi" />
            <AppShell title="Log Verifikasi" maxWidth="max-w-6xl" actions={<BackLink href="/admin/dashboard">Dashboard</BackLink>}>
                <SectionHeading
                    title="Riwayat verifikasi"
                    description="Rekam hasil pemeriksaan token, dokumen, dan public key."
                    action={<span className="text-sm font-medium text-zinc-500">{logs.total} entri</span>}
                />
                <div className="ui-card mt-4 overflow-hidden">
                    {logs.data.length === 0 ? (
                        <EmptyState title="Belum ada log verifikasi" description="Aktivitas verifikasi akan tampil di sini." />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="ui-table">
                                <thead><tr><th>Waktu</th><th>Token</th><th>Metode</th><th>Dokumen</th><th>Hasil</th></tr></thead>
                                <tbody>
                                    {logs.data.map((log) => (
                                        <tr key={log.id}>
                                            <td className="whitespace-nowrap text-zinc-600">{new Date(log.created_at).toLocaleString('id-ID')}</td>
                                            <td className="font-mono text-xs text-zinc-700">{log.token_prefix}...</td>
                                            <td>{METHOD_LABELS[log.method] ?? log.method}</td>
                                            <td>
                                                {log.document_id !== null ? (
                                                    <Link href={`/documents/${log.document_id}`} className="ui-link whitespace-nowrap">
                                                        {log.document_title ?? log.document_id}
                                                    </Link>
                                                ) : <span className="text-zinc-400">Tidak tersedia</span>}
                                            </td>
                                            <td><StatusBadge status={log.result_code} /></td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {logs.last_page > 1 && (
                    <div className="mt-4 flex flex-wrap items-center justify-between gap-3">
                        <span className="text-sm text-zinc-500">Halaman {logs.current_page} dari {logs.last_page}</span>
                        <div className="flex gap-2">
                            <button type="button" onClick={() => visitPage(logs.current_page - 1)} disabled={logs.current_page === 1} className="ui-button-secondary">
                                Sebelumnya
                            </button>
                            <button type="button" onClick={() => visitPage(logs.current_page + 1)} disabled={logs.current_page === logs.last_page} className="ui-button-secondary">
                                Berikutnya
                            </button>
                        </div>
                    </div>
                )}
            </AppShell>
        </>
    );
}
