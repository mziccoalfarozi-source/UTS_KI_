import { Head, Link } from '@inertiajs/react';

export default function DocumentShow({ document }) {
    return (
        <>
            <Head title={document.title} />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Detail Dokumen</h1>
                        </div>
                        <Link href="/documents" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Kembali
                        </Link>
                    </div>
                </header>

                <div className="mx-auto flex max-w-5xl flex-col gap-6 px-5 py-8">
                    <section className="border border-zinc-200 bg-white p-6 shadow-sm">
                        <div className="flex flex-col gap-2 border-b border-zinc-200 pb-5">
                            <h2 className="text-xl font-semibold">{document.title}</h2>
                            <p className="text-sm text-zinc-600">{document.institution}</p>
                        </div>
                        <dl className="mt-5 grid gap-5 sm:grid-cols-2">
                            <Item label="Tanggal" value={document.document_date} />
                            <Item label="Status" value={document.status} />
                            <Item label="File asli" value={document.original_filename} />
                            <Item label="Ukuran final" value={`${document.file_size} byte`} />
                            <Item label="Dibuat oleh" value={document.created_by} />
                            <Item label="Verification token" value={document.verification_token} mono />
                            <div className="sm:col-span-2">
                                <Item label="Document hash" value={document.document_hash} mono />
                            </div>
                            <div className="sm:col-span-2">
                                <Item label="Verification URL" value={document.verification_url} mono />
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
                        <h2 className="mb-3 text-base font-semibold">Daftar penandatangan</h2>
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
                                        <tr key={signer.id}>
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
