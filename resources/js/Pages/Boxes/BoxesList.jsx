import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function BoxesList({ auth, boxes }) {

    const handleRowClick = (boxUuid) => {
        router.visit(route('boxes.show', boxUuid));
    };

    const handleDelete = (e, boxUuid, boxName) => {
        e.stopPropagation();
        if (confirm(`「${boxName}」を本当に削除しますか？この操作は元に戻せません。BOX内の写真も全て削除されます。`)) {
            router.delete(route('boxes.destroy', boxUuid), {
                preserveScroll: true, // Optional: to maintain scroll position after delete
                // onSuccess: () => { /* Optional: handle success client-side */ },
            });
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">BOX一覧</h2>}
        >
            <Head title="BOX一覧" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-4">
                        <Link href={route('boxes.create')} className="btn btn-primary">
                            新規BOX作成
                        </Link>
                    </div>
                    <div className="bg-base-100 shadow-sm sm:rounded-lg overflow-x-auto">
                        {boxes.length > 0 ? (
                            <table className="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th className="w-20">画像</th>
                                        <th>名前</th>
                                        <th>説明</th>
                                        <th>更新日時</th>
                                        <th>操作</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {boxes.map((box) => (
                                        <tr
                                            key={box.uuid}
                                            onClick={() => handleRowClick(box.uuid)}
                                            className="hover cursor-pointer"
                                        >
                                            <td>
                                                {box.first_photo_url_public ? (
                                                    <img
                                                        src={box.first_photo_url_public}
                                                        alt={box.name}
                                                        className="w-16 h-16 object-cover rounded"
                                                    />
                                                ) : <div className="w-16 h-16 bg-base-200 rounded flex items-center justify-center text-xs text-base-content/50">画像なし</div>}
                                            </td>
                                            <td>{box.name}</td>
                                            <td className="whitespace-pre-wrap max-w-xs truncate">{box.description}</td> {/* 説明が長い場合に省略 */}
                                            <td>{new Date(box.updated_at).toLocaleString()}</td>
                                            <td className="whitespace-nowrap">
                                                <div className="flex items-center gap-1">
                                                    <Link
                                                        href={route('boxes.show', box.uuid)}
                                                        className="btn btn-xs btn-outline btn-accent"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        詳細
                                                    </Link>
                                                    <Link
                                                        href={route('boxes.edit', box.uuid)}
                                                        className="btn btn-xs btn-outline btn-info"
                                                        onClick={(e) => e.stopPropagation()}
                                                    >
                                                        編集
                                                    </Link>
                                                    <button
                                                        onClick={(e) => handleDelete(e, box.uuid, box.name)}
                                                        className="btn btn-xs btn-outline btn-error"
                                                    >
                                                        削除
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        ) : (
                            <div className="p-6 text-base-content">表示するBOXがありません。</div>
                        )}
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}