import { Head, Link } from '@inertiajs/react';

export default function SignerDocumentIndex({ documents, hasSigningKey }) {
    return (
        <>
            <Head title="Dokumen Saya" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-5xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Dokumen Saya</h1>
                        </div>
                        <Link
                            href="/signer/dashboard"
                            className="text-sm font-semibold text-zinc-700 hover:text-zinc-950"
                        >
                            Dashboard
                        </Link>
                    </div>
                </header>

                <section className="mx-auto flex max-w-5xl flex-col gap-5 px-5 py-8">
                    {!hasSigningKey && (
                        <div className="border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
                            Signing key belum tersedia. Buat signing key sebelum menandatangani dokumen.
                        </div>
                    )}

                    <div className="overflow-x-auto border border-zinc-200 bg-white shadow-sm">
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                                <tr>
                                    <th className="px-4 py-3">Dokumen</th>
                                    <th className="px-4 py-3">Urutan</th>
                                    <th className="px-4 py-3">Assignment</th>
                                    <th className="px-4 py-3">Dokumen</th>
                                    <th className="px-4 py-3">Akses</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-zinc-200">
                                {documents.map((document) => (
                                    <tr key={document.id}>
                                        <td className="px-4 py-3">
                                            <p className="font-semibold">{document.title}</p>
                                            <p className="text-zinc-500">{document.institution}</p>
                                        </td>
                                        <td className="px-4 py-3">{document.sign_order}</td>
                                        <td className="px-4 py-3">{document.assignment_status}</td>
                                        <td className="px-4 py-3">{document.document_status}</td>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={`/documents/${document.id}`}
                                                className="font-semibold text-emerald-700 hover:text-emerald-800"
                                            >
                                                {document.can_sign ? 'Tandatangani' : 'Lihat detail'}
                                            </Link>
                                        </td>
                                    </tr>
                                ))}
                                {documents.length === 0 && (
                                    <tr>
                                        <td className="px-4 py-8 text-center text-zinc-500" colSpan="5">
                                            Belum ada dokumen yang ditugaskan.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </main>
        </>
    );
}
