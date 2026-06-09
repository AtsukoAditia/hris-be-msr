import { Edit2, Trash2 } from 'lucide-react'

const ShiftList = ({ shifts, isLoading, onEdit, onDelete }) => {
  const formatTime = (time) => {
    if (!time) return '-'
    return String(time).substring(0, 5)
  }

  if (isLoading) {
    return <div className="p-8 text-center text-gray-500">Memuat data shift...</div>
  }

  if (!shifts || shifts.length === 0) {
    return <div className="p-8 text-center text-gray-500">Belum ada data shift</div>
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full">
        <thead className="bg-gray-50 border-b border-gray-200">
          <tr>
            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shift</th>
            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jam</th>
            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Toleransi</th>
            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipe</th>
            <th className="px-6 py-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th className="px-6 py-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Aksi</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {shifts.map((shift) => (
            <tr key={shift.id} className="hover:bg-gray-50 transition-colors">
              <td className="px-6 py-4">
                <div className="font-medium text-gray-900">{shift.name || '-'}</div>
                <div className="text-sm text-gray-500">Kode: {shift.code || '-'}</div>
                {shift.description && <div className="text-xs text-gray-400 mt-1 max-w-xs truncate">{shift.description}</div>}
              </td>
              <td className="px-6 py-4 text-sm text-gray-900">
                {formatTime(shift.start_time)} - {formatTime(shift.end_time)}
              </td>
              <td className="px-6 py-4 text-sm text-gray-900">
                {shift.late_tolerance ?? 0} menit
              </td>
              <td className="px-6 py-4">
                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${shift.is_overnight ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'}`}>
                  {shift.is_overnight ? 'Overnight' : 'Reguler'}
                </span>
              </td>
              <td className="px-6 py-4">
                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${shift.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}>
                  {shift.is_active ? 'Aktif' : 'Nonaktif'}
                </span>
              </td>
              <td className="px-6 py-4 text-right">
                <div className="flex items-center justify-end space-x-2">
                  <button
                    type="button"
                    onClick={() => onEdit(shift)}
                    className="p-2 text-gray-400 hover:text-yellow-600 transition-colors"
                    title="Edit"
                  >
                    <Edit2 className="w-4 h-4" />
                  </button>
                  <button
                    type="button"
                    onClick={() => onDelete(shift)}
                    className="p-2 text-gray-400 hover:text-red-600 transition-colors"
                    title="Hapus / Nonaktifkan"
                  >
                    <Trash2 className="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export default ShiftList
