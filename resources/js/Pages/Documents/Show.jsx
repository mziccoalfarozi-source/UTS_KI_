import AppShell, { BackLink } from '../../Components/AppShell';
import { Card, MetaItem, SectionHeading, StatusBadge } from '../../Components/Ui';
import { Head } from '@inertiajs/react';

export default function DocumentShow({ document }) {
    return (
        <>
            <Head title={document.title} />
            <AppShell title="Detail Dokumen" actions={<BackLink href="/documents">Daftar dokumen</BackLink>} maxWidth="max-w-5xl">
                <div className="flex flex-col gap-6">
                    <Card className="p-5 sm:p-6">
                        <div className="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                            <div className="min-w-0">
                                <h2 className="text-xl font-semibold text-zinc-950">{document.title}</h2>
                                <p className="mt-1 text-sm text-zinc-600">{document.institution}</p>
                            </div>
                            <StatusBadge status={document.status} />
                        </div>
                        <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                            <MetaItem label="Tanggal" value={document.document_date} />
                            <MetaItem label="File asli" value={document.original_filename} />
                            <MetaItem label="Ukuran final" value={`${document.file_size} byte`} />
                            <MetaItem label="Dibuat oleh" value={document.created_by} />
                            <MetaItem label="Verification token" value={document.verification_token} mono />
                            <MetaItem label="Document hash" value={document.document_hash} mono />
                            <div className="sm:col-span-2">
                                <MetaItem label="Verification URL" value={document.verification_url} mono />
                            </div>
                        </dl>
                        <a href={`/documents/${document.id}/download`} className="ui-button-primary mt-6">
                            Download PDF final
                        </a>
                    </Card>

                    <section>
                        <SectionHeading title="Daftar penandatangan" description="Urutan dan progres signer pada dokumen ini." />
                        <div className="ui-card mt-4 overflow-hidden p-0">
                            <div className="overflow-x-auto">
                                <table className="ui-table">
                                    <thead>
                                        <tr>
                                            <th>Urutan</th>
                                            <th>Nama</th>
                                            <th>Jabatan</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {document.signers.map((signer) => (
                                            <tr key={signer.id}>
                                                <td className="font-semibold">{signer.sign_order}</td>
                                                <td className="font-medium text-zinc-950">{signer.name}</td>
                                                <td className="text-zinc-600">{signer.position_title}</td>
                                                <td><StatusBadge status={signer.status} /></td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </section>
                </div>
            </AppShell>
        </>
    );
}
