import AppShell, { BackLink } from '../../../Components/AppShell';
import { Alert, Card, FieldError, MetaItem, SectionHeading, StatusBadge } from '../../../Components/Ui';
import { Head, useForm } from '@inertiajs/react';

export default function SignerDocumentShow({ document, assignment }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        signing_passphrase: '',
    });

    function submit(event) {
        event.preventDefault();
        post(`/documents/${document.id}/sign`, {
            preserveScroll: true,
            onFinish: () => reset('signing_passphrase'),
        });
    }

    return (
        <>
            <Head title={document.title} />
            <AppShell title="Detail Assignment" actions={<BackLink href="/documents">Dokumen saya</BackLink>} maxWidth="max-w-5xl">
                <div className="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <Card className="p-5 sm:p-6">
                            <div className="flex flex-col gap-4 border-b border-zinc-200 pb-5 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 className="text-xl font-semibold text-zinc-950">{document.title}</h2>
                                    <p className="mt-1 text-sm text-zinc-600">{document.institution}</p>
                                </div>
                                <StatusBadge status={document.status} />
                            </div>
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                <MetaItem label="Tanggal" value={document.document_date} />
                                <MetaItem label="File" value={document.original_filename} />
                                <MetaItem label="Urutan Anda" value={assignment.sign_order} />
                                <MetaItem label="Status assignment"><StatusBadge status={assignment.status} /></MetaItem>
                                <div className="sm:col-span-2">
                                    <MetaItem label="Document hash" value={document.document_hash} mono />
                                </div>
                            </dl>
                            <a href={`/documents/${document.id}/download`} className="ui-button-secondary mt-6">Download PDF final</a>
                        </Card>

                        <section>
                            <SectionHeading title="Urutan penandatangan" description="Setiap signer dapat menandatangani setelah urutan sebelumnya selesai." />
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
                                                <tr key={signer.sign_order}>
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

                    <Card className="p-5 sm:p-6 lg:sticky lg:top-6">
                        <SectionHeading title="Digital Signing" description={`Urutan assignment: ${assignment.sign_order}`} />
                        <div className="mt-4"><StatusBadge status={assignment.status} /></div>

                        {assignment.can_sign && (
                            <form className="mt-5 flex flex-col gap-4" onSubmit={submit}>
                                <div className="flex flex-col gap-2">
                                    <label htmlFor="signing-passphrase" className="text-sm font-medium text-zinc-800">Signing passphrase</label>
                                    <input id="signing-passphrase" type="password" value={data.signing_passphrase} onChange={(event) => setData('signing_passphrase', event.target.value)} autoComplete="off" required className="ui-input" />
                                    <p className="text-xs leading-5 text-zinc-500">Passphrase digunakan untuk membuka signing key saat proses ini dan tidak disimpan.</p>
                                    <FieldError>{errors.signing_passphrase}</FieldError>
                                    <FieldError>{errors.signing}</FieldError>
                                </div>
                                <button type="submit" disabled={processing} className="ui-button-primary w-full">
                                    {processing ? 'Menandatangani...' : 'Tandatangani dokumen'}
                                </button>
                            </form>
                        )}

                        {!assignment.can_sign && assignment.waiting_for_previous && (
                            <div className="mt-5"><Alert tone="warning">Menunggu signer pada urutan sebelumnya.</Alert></div>
                        )}
                        {!assignment.can_sign && !assignment.has_signing_key && (
                            <div className="mt-5"><Alert tone="warning">Signing key belum tersedia.</Alert></div>
                        )}
                        {assignment.status === 'SIGNED' && (
                            <div className="mt-5"><Alert tone="success">Assignment telah ditandatangani.</Alert></div>
                        )}
                    </Card>
                </div>
            </AppShell>
        </>
    );
}
