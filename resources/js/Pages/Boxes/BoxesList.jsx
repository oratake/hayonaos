import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, router } from '@inertiajs/react';

export default function BoxesList({ auth, boxes }) {

    const handleRowClick = (boxId) => {
        router.visit(route('boxes.show', boxId));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">BOX一覧</h2>}
        >
            <Head title="BOX一覧" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-base-100 shadow-sm sm:rounded-lg overflow-x-auto">
                        {boxes.length > 0 ? (
                            <table className="table table-zebra w-full">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>名前</th>
                                        <th>説明</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {boxes.map((box) => (
                                        <tr
                                            key={box.id}
                                            onClick={() => handleRowClick(box.id)}
                                            className="hover cursor-pointer"
                                        >
                                            <td>{box.id}</td>
                                            <td>{box.name}</td>
                                            <td>{box.description}</td>
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