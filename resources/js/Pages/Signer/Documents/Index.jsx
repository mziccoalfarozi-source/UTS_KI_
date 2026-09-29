import AppShell, { BackLink } from '../../../Components/AppShell';
import { Alert, EmptyState, SectionHeading, StatusBadge } from '../../../Components/Ui';
import { Head, Link } from '@inertiajs/react';

export default function SignerDocumentIndex({ documents, hasSigningKey }) {
    return (
        <>
            <Head title="Dokumen Saya" />
            <AppShell title="Dokumen Saya" actions={<BackLink href="/signer/dashboard">Dashboard</BackLink>} maxWidth="max-w-5xl">
                <div className="flex flex-col gap-5">
                    {!hasSigningKey && (
                        <Alert tone="warning">
                            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                                <span>Signing key belum tersedia. Buat key sebelum menandatangani dokumen.</span>
                                <Link href="/keys" className="font-semibold underline underline-offset-2">Buat signing key</Link>
                            </div>
                        </Alert>
                    )}

                    <SectionHeading title="Assignment penandatanganan" description="Dokumen yang ditugaskan kepada akun Anda." />
                    <div className="ui-card overflow-hidden p-0">
                        {documents.length > 0 ? (
                            <div className="overflow-x-auto">
                                <table className="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Dokumen</th>
                                            <th>Urutan</th>
                                            <th>Assignment</th>
                                            <th>Status dokumen</th>
                                            <th><span className="sr-only">Aksi</span></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {documents.map((document) => (
                                            <tr key={document.id}>
                                                <td>
                                                    <p className="font-semibold text-zinc-950">{document.title}</p>
                                                    <p className="mt-0.5 text-xs text-zinc-500">{document.institution}</p>
                                                </td>
                                                <td className="font-semibold">{document.sign_order}</td>
                                                <td><StatusBadge status={document.assignment_status} /></td>
                                                <td><StatusBadge status={document.document_status} /></td>
                                                <td className="text-right">
                                                    <Link href={`/documents/${document.id}`} className="ui-link whitespace-nowrap">
                                                        {document.can_sign ? 'Tandatangani' : 'Lihat detail'}
                                                    </Link>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <EmptyState title="Belum ada assignment" description="Dokumen yang ditugaskan kepada Anda akan tampil di sini." />
                        )}
                    </div>
                </div>
            </AppShell>
        </>
    );
}
