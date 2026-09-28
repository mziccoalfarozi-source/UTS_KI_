import { Head, Link, useForm } from '@inertiajs/react';

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
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Detail Assignment</h1>
                        </div>
                        <Link href="/documents" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Kembali
                        </Link>
                    </div>
                </header>

                <div className="mx-auto grid max-w-5xl gap-6 px-5 py-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="flex min-w-0 flex-col gap-6">
                        <section className="border border-zinc-200 bg-white p-6 shadow-sm">
                            <h2 className="text-xl font-semibold">{document.title}</h2>
                            <p className="mt-1 text-sm text-zinc-600">{document.institution}</p>
                            <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                                <Item label="Tanggal" value={document.document_date} />
                                <Item label="Status dokumen" value={document.status} />
                                <Item label="File" value={document.original_filename} />
                                <Item label="Urutan Anda" value={assignment.sign_order} />
                                <div className="sm:col-span-2">
                                    <Item label="Document hash" value={document.document_hash} mono />
                                </div>
                            </dl>
                            <a
                                href={`/documents/${document.id}/download`}
                                className="mt-6 inline-flex h-10 items-center bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800"
                            >
                                Download PDF final
                            </a>
                        </section>

                        <section>
                            <h2 className="mb-3 text-base font-semibold">Urutan penandatangan</h2>
                            <div className="overflow-x-auto border border-zinc-200 bg-white shadow-sm">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                                        <tr>
                                            <th className="px-4 py-3">Urutan</th>
                                            <th className="px-4 py-3">Nama</th>
                                            <th className="px-4 py-3">Jabatan</th>
                                            <th className="px-4 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-zinc-200">
                                        {document.signers.map((signer) => (
                                            <tr key={signer.sign_order}>
                                                <td className="px-4 py-3">{signer.sign_order}</td>
                                                <td className="px-4 py-3 font-medium">{signer.name}</td>
                                                <td className="px-4 py-3">{signer.position_title}</td>
                                                <td className="px-4 py-3">{signer.status}</td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>

                    <aside className="h-fit border border-zinc-200 bg-white p-5 shadow-sm">
                        <h2 className="font-semibold">Digital Signing</h2>
                        <p className="mt-1 text-sm text-zinc-600">Status assignment: {assignment.status}</p>

                        {assignment.can_sign && (
                            <form className="mt-5 flex flex-col gap-3" onSubmit={submit}>
                                <label className="flex flex-col gap-2 text-sm font-medium">
                                    Signing passphrase
                                    <input
                                        type="password"
                                        value={data.signing_passphrase}
                                        onChange={(event) => setData('signing_passphrase', event.target.value)}
                                        autoComplete="off"
                                        required
                                        className="h-10 border border-zinc-300 px-3 outline-none focus:border-emerald-700"
                                    />
                                </label>
                                {errors.signing_passphrase && (
                                    <p className="text-sm text-red-700">{errors.signing_passphrase}</p>
                                )}
                                {errors.signing && <p className="text-sm text-red-700">{errors.signing}</p>}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="h-10 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:opacity-60"
                                >
                                    {processing ? 'Menandatangani...' : 'Tandatangani'}
                                </button>
                            </form>
                        )}

                        {!assignment.can_sign && assignment.waiting_for_previous && (
                            <p className="mt-4 text-sm text-amber-800">Menunggu signer sebelumnya.</p>
                        )}
                        {!assignment.can_sign && !assignment.has_signing_key && (
                            <p className="mt-4 text-sm text-amber-800">Signing key belum tersedia.</p>
                        )}
                        {assignment.status === 'SIGNED' && (
                            <p className="mt-4 text-sm text-emerald-700">Assignment telah ditandatangani.</p>
                        )}
                    </aside>
                </div>
            </main>
        </>
    );
}

function Item({ label, value, mono = false }) {
    return (
        <div className="flex min-w-0 flex-col gap-1">
            <dt className="text-xs font-semibold uppercase text-zinc-500">{label}</dt>
            <dd className={`break-all text-sm font-medium ${mono ? 'font-mono' : ''}`}>{value}</dd>
        </div>
    );
}
