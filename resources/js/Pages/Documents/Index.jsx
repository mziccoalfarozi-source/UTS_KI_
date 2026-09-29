import AppShell, { BackLink } from '../../Components/AppShell';
import { Card, EmptyState, FieldError, SectionHeading, StatusBadge } from '../../Components/Ui';
import { Head, Link, useForm } from '@inertiajs/react';

export default function DocumentIndex({ documents, availableSigners }) {
    const initialSigner = availableSigners[0];
    const { data, setData, post, processing, errors, reset } = useForm({
        title: '',
        institution: '',
        document_date: '',
        pdf: null,
        signers: initialSigner
            ? [
                  {
                      user_id: initialSigner.id,
                      sign_order: 1,
                      position_title: initialSigner.position_title ?? '',
                  },
              ]
            : [],
    });

    function updateSigner(index, field, value) {
        const signers = data.signers.map((signer, signerIndex) => {
            if (signerIndex !== index) {
                return signer;
            }

            if (field === 'user_id') {
                const selected = availableSigners.find((item) => item.id === Number(value));

                return {
                    ...signer,
                    user_id: Number(value),
                    position_title: selected?.position_title ?? '',
                };
            }

            return { ...signer, [field]: value };
        });

        setData('signers', signers);
    }

    function addSigner() {
        const assignedIds = new Set(data.signers.map((signer) => signer.user_id));
        const nextSigner = availableSigners.find((signer) => !assignedIds.has(signer.id));

        if (!nextSigner) {
            return;
        }

        setData('signers', [
            ...data.signers,
            {
                user_id: nextSigner.id,
                sign_order: data.signers.length + 1,
                position_title: nextSigner.position_title ?? '',
            },
        ]);
    }

    function removeSigner(index) {
        setData(
            'signers',
            data.signers
                .filter((_, signerIndex) => signerIndex !== index)
                .map((signer, signerIndex) => ({ ...signer, sign_order: signerIndex + 1 })),
        );
    }

    function submit(event) {
        event.preventDefault();
        post('/documents', {
            forceFormData: true,
            onSuccess: () => reset(),
        });
    }

    return (
        <>
            <Head title="Dokumen" />
            <AppShell title="Manajemen Dokumen" actions={<BackLink href="/admin/dashboard">Dashboard</BackLink>}>
                <div className="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_400px]">
                    <section className="min-w-0">
                        <SectionHeading
                            title="Daftar dokumen"
                            description="Dokumen final dan progres penandatanganannya."
                            action={<span className="text-sm font-medium text-zinc-500">{documents.length} dokumen</span>}
                        />
                        <div className="ui-card mt-4 overflow-hidden p-0">
                            {documents.length > 0 ? (
                                <div className="overflow-x-auto">
                                    <table className="ui-table">
                                        <thead>
                                            <tr>
                                                <th>Dokumen</th>
                                                <th>Tanggal</th>
                                                <th>Status</th>
                                                <th className="text-center">Signer</th>
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
                                                    <td className="whitespace-nowrap text-zinc-600">{document.document_date}</td>
                                                    <td><StatusBadge status={document.status} /></td>
                                                    <td className="text-center font-medium">{document.signers_count}</td>
                                                    <td className="text-right">
                                                        <Link href={`/documents/${document.id}`} className="ui-link whitespace-nowrap">
                                                            Lihat detail
                                                        </Link>
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            ) : (
                                <EmptyState title="Belum ada dokumen" description="Dokumen yang difinalisasi akan tampil di sini." />
                            )}
                        </div>
                    </section>

                    <Card className="p-5 sm:p-6">
                        <SectionHeading title="Buat dokumen" description="Upload PDF dan tetapkan urutan penandatangan." />
                        <form className="mt-6 flex flex-col gap-5" onSubmit={submit}>
                            <Field id="title" label="Judul" error={errors.title}>
                                <input id="title" value={data.title} onChange={(event) => setData('title', event.target.value)} required className="ui-input" />
                            </Field>
                            <Field id="institution" label="Institusi" error={errors.institution}>
                                <input id="institution" value={data.institution} onChange={(event) => setData('institution', event.target.value)} required className="ui-input" />
                            </Field>
                            <Field id="document-date" label="Tanggal dokumen" error={errors.document_date}>
                                <input id="document-date" type="date" value={data.document_date} onChange={(event) => setData('document_date', event.target.value)} required className="ui-input" />
                            </Field>
                            <Field id="document-pdf" label="PDF final (maksimum 10 MiB)" error={errors.pdf}>
                                <input id="document-pdf" type="file" accept="application/pdf" onChange={(event) => setData('pdf', event.target.files[0] ?? null)} required className="block w-full rounded-md border border-zinc-300 bg-white text-sm text-zinc-600 file:mr-3 file:border-0 file:bg-zinc-900 file:px-3 file:py-2.5 file:font-semibold file:text-white" />
                            </Field>

                            <fieldset className="flex flex-col gap-3">
                                <legend className="sr-only">Penandatangan</legend>
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-semibold text-zinc-900">Penandatangan</span>
                                    <button type="button" onClick={addSigner} disabled={data.signers.length >= availableSigners.length} className="ui-link disabled:cursor-not-allowed disabled:text-zinc-400">
                                        + Tambah signer
                                    </button>
                                </div>
                                <FieldError>{errors.signers}</FieldError>
                                {data.signers.map((signer, index) => (
                                    <div key={index} className="rounded-md border border-zinc-200 bg-zinc-50 p-4">
                                        <p className="mb-3 text-xs font-semibold text-zinc-500 uppercase">Signer {index + 1}</p>
                                        <div className="grid gap-3 sm:grid-cols-[88px_1fr]">
                                            <Field id={`sign-order-${index}`} label="Urutan" error={errors[`signers.${index}.sign_order`]}>
                                                <input id={`sign-order-${index}`} type="number" min="1" value={signer.sign_order} onChange={(event) => updateSigner(index, 'sign_order', Number(event.target.value))} className="ui-input" />
                                            </Field>
                                            <Field id={`signer-${index}`} label="Nama signer" error={errors[`signers.${index}.user_id`]}>
                                                <select id={`signer-${index}`} value={signer.user_id} onChange={(event) => updateSigner(index, 'user_id', event.target.value)} className="ui-input">
                                                    {availableSigners.map((option) => <option key={option.id} value={option.id}>{option.name}</option>)}
                                                </select>
                                            </Field>
                                        </div>
                                        <div className="mt-3">
                                            <Field id={`position-${index}`} label="Jabatan pada dokumen" error={errors[`signers.${index}.position_title`]}>
                                                <input id={`position-${index}`} value={signer.position_title} onChange={(event) => updateSigner(index, 'position_title', event.target.value)} required className="ui-input" />
                                            </Field>
                                        </div>
                                        {data.signers.length > 1 && (
                                            <button type="button" onClick={() => removeSigner(index)} className="mt-3 text-sm font-semibold text-red-700 hover:text-red-800">Hapus signer</button>
                                        )}
                                    </div>
                                ))}
                                {availableSigners.length === 0 && <p className="text-sm text-amber-800">Belum ada signer yang dapat ditugaskan.</p>}
                            </fieldset>

                            <button type="submit" disabled={processing || availableSigners.length === 0} className="ui-button-primary w-full">
                                {processing ? 'Memfinalisasi...' : 'Finalisasi dokumen'}
                            </button>
                        </form>
                    </Card>
                </div>
            </AppShell>
        </>
    );
}

function Field({ id, label, error, children }) {
    return (
        <div className="flex flex-col gap-2">
            <label htmlFor={id} className="text-sm font-medium text-zinc-800">{label}</label>
            {children}
            <FieldError>{error}</FieldError>
        </div>
    );
}
