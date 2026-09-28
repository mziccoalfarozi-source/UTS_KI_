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
            <Head title="Documents" />
            <main className="min-h-screen bg-zinc-100">
                <header className="border-b border-zinc-200 bg-white">
                    <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-4">
                        <div>
                            <p className="text-sm font-semibold text-emerald-700">Secure Document Signature</p>
                            <h1 className="text-lg font-semibold">Document Management</h1>
                        </div>
                        <Link href="/admin/dashboard" className="text-sm font-semibold text-zinc-700 hover:text-zinc-950">
                            Dashboard
                        </Link>
                    </div>
                </header>

                <div className="mx-auto grid max-w-6xl gap-8 px-5 py-8 lg:grid-cols-[minmax(0,1fr)_minmax(340px,0.7fr)]">
                    <section className="min-w-0">
                        <div className="mb-4 flex items-center justify-between gap-4">
                            <h2 className="text-base font-semibold">Daftar dokumen</h2>
                            <span className="text-sm text-zinc-500">{documents.length} dokumen</span>
                        </div>
                        <div className="overflow-x-auto border border-zinc-200 bg-white shadow-sm">
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-zinc-200 bg-zinc-50 text-xs uppercase text-zinc-500">
                                    <tr>
                                        <th className="px-4 py-3 font-semibold">Dokumen</th>
                                        <th className="px-4 py-3 font-semibold">Tanggal</th>
                                        <th className="px-4 py-3 font-semibold">Status</th>
                                        <th className="px-4 py-3 font-semibold">Signer</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-zinc-200">
                                    {documents.map((document) => (
                                        <tr key={document.id}>
                                            <td className="px-4 py-3">
                                                <Link
                                                    href={`/documents/${document.id}`}
                                                    className="font-semibold text-emerald-700 hover:text-emerald-800"
                                                >
                                                    {document.title}
                                                </Link>
                                                <p className="text-zinc-500">{document.institution}</p>
                                            </td>
                                            <td className="px-4 py-3">{document.document_date}</td>
                                            <td className="px-4 py-3 font-medium">{document.status}</td>
                                            <td className="px-4 py-3">{document.signers_count}</td>
                                        </tr>
                                    ))}
                                    {documents.length === 0 && (
                                        <tr>
                                            <td className="px-4 py-8 text-center text-zinc-500" colSpan="4">
                                                Belum ada dokumen.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="border border-zinc-200 bg-white p-6 shadow-sm">
                        <h2 className="text-base font-semibold">Buat dokumen</h2>
                        <form className="mt-5 flex flex-col gap-5" onSubmit={submit}>
                            <Field label="Judul" error={errors.title}>
                                <input
                                    value={data.title}
                                    onChange={(event) => setData('title', event.target.value)}
                                    required
                                    className="h-10 border border-zinc-300 px-3 outline-none focus:border-emerald-700"
                                />
                            </Field>
                            <Field label="Institusi" error={errors.institution}>
                                <input
                                    value={data.institution}
                                    onChange={(event) => setData('institution', event.target.value)}
                                    required
                                    className="h-10 border border-zinc-300 px-3 outline-none focus:border-emerald-700"
                                />
                            </Field>
                            <Field label="Tanggal dokumen" error={errors.document_date}>
                                <input
                                    type="date"
                                    value={data.document_date}
                                    onChange={(event) => setData('document_date', event.target.value)}
                                    required
                                    className="h-10 border border-zinc-300 px-3 outline-none focus:border-emerald-700"
                                />
                            </Field>
                            <Field label="PDF (maksimum 10 MiB)" error={errors.pdf}>
                                <input
                                    type="file"
                                    accept="application/pdf"
                                    onChange={(event) => setData('pdf', event.target.files[0] ?? null)}
                                    required
                                    className="block w-full text-sm file:mr-3 file:border-0 file:bg-zinc-900 file:px-3 file:py-2 file:font-semibold file:text-white"
                                />
                            </Field>

                            <div className="flex flex-col gap-3">
                                <div className="flex items-center justify-between gap-3">
                                    <span className="text-sm font-medium">Penandatangan</span>
                                    <button
                                        type="button"
                                        onClick={addSigner}
                                        disabled={data.signers.length >= availableSigners.length}
                                        className="text-sm font-semibold text-emerald-700 disabled:text-zinc-400"
                                    >
                                        Tambah signer
                                    </button>
                                </div>
                                {errors.signers && <span className="text-sm text-red-700">{errors.signers}</span>}
                                {data.signers.map((signer, index) => (
                                    <div key={index} className="grid gap-3 border border-zinc-200 p-3">
                                        <div className="grid grid-cols-[56px_1fr] gap-3">
                                            <input
                                                type="number"
                                                min="1"
                                                value={signer.sign_order}
                                                onChange={(event) =>
                                                    updateSigner(index, 'sign_order', Number(event.target.value))
                                                }
                                                className="h-10 border border-zinc-300 px-2"
                                                aria-label="Urutan signer"
                                            />
                                            <select
                                                value={signer.user_id}
                                                onChange={(event) => updateSigner(index, 'user_id', event.target.value)}
                                                className="h-10 border border-zinc-300 px-2"
                                            >
                                                {availableSigners.map((option) => (
                                                    <option key={option.id} value={option.id}>
                                                        {option.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <input
                                            value={signer.position_title}
                                            onChange={(event) =>
                                                updateSigner(index, 'position_title', event.target.value)
                                            }
                                            placeholder="Jabatan pada dokumen"
                                            required
                                            className="h-10 border border-zinc-300 px-3"
                                        />
                                        {data.signers.length > 1 && (
                                            <button
                                                type="button"
                                                onClick={() => removeSigner(index)}
                                                className="justify-self-start text-sm font-semibold text-red-700"
                                            >
                                                Hapus signer
                                            </button>
                                        )}
                                    </div>
                                ))}
                            </div>

                            <button
                                type="submit"
                                disabled={processing || availableSigners.length === 0}
                                className="h-11 bg-zinc-900 px-4 text-sm font-semibold text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-60"
                            >
                                {processing ? 'Memfinalisasi...' : 'Finalisasi dokumen'}
                            </button>
                        </form>
                    </section>
                </div>
            </main>
        </>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="flex flex-col gap-2 text-sm font-medium">
            {label}
            {children}
            {error && <span className="font-normal text-red-700">{error}</span>}
        </label>
    );
}
