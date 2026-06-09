import { useEffect, useState } from 'react'
import shiftService from '../../services/shiftService'

const initialFormData = {
  name: '',
  code: '',
  start_time: '',
  end_time: '',
  late_tolerance: 15,
  is_overnight: false,
  description: '',
  is_active: true,
}

export default function ShiftFormModal({ shift, onClose, onSaveSuccess }) {
  const [loading, setLoading] = useState(false)
  const [formData, setFormData] = useState(initialFormData)

  useEffect(() => {
    if (shift) {
      setFormData({
        name: shift.name || '',
        code: shift.code || '',
        start_time: formatTimeInput(shift.start_time),
        end_time: formatTimeInput(shift.end_time),
        late_tolerance: shift.late_tolerance ?? 15,
        is_overnight: Boolean(shift.is_overnight),
        description: shift.description || '',
        is_active: shift.is_active !== false,
      })
    } else {
      setFormData(initialFormData)
    }
  }, [shift])

  const handleChange = (e) => {
    const { name, value, type, checked } = e.target
    setFormData((prev) => ({
      ...prev,
      [name]: type === 'checkbox' ? checked : value,
    }))
  }

  const handleSubmit = async (e) => {
    e.preventDefault()

    if (!formData.is_overnight && formData.end_time <= formData.start_time) {
      alert('Jam selesai harus lebih besar dari jam mulai untuk shift reguler.')
      return
    }

    const payload = {
      name: formData.name.trim(),
      code: formData.code.trim().toUpperCase(),
      start_time: formData.start_time,
      end_time: formData.end_time,
      late_tolerance: Number(formData.late_tolerance || 0),
      is_overnight: Boolean(formData.is_overnight),
      description: formData.description.trim(),
      is_active: Boolean(formData.is_active),
    }

    setLoading(true)
    try {
      const res = shift
        ? await shiftService.update(shift.id, payload)
        : await shiftService.create(payload)

      onSaveSuccess?.(res.data?.message || 'Shift berhasil disimpan')
    } catch (error) {
      alert(error.response?.data?.message || 'Gagal menyimpan shift')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
      <div className="w-full max-w-2xl rounded-xl bg-white shadow-xl max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">{shift ? 'Edit Shift' : 'Tambah Shift'}</h2>
            <p className="text-sm text-gray-500 mt-1">Atur jam kerja untuk jadwal dan validasi absensi.</p>
          </div>
          <button type="button" onClick={onClose} className="text-gray-400 hover:text-gray-600">
            ✕
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-6">
          <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Nama Shift *</label>
              <input
                type="text"
                name="name"
                value={formData.name}
                onChange={handleChange}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                placeholder="Contoh: Shift Pagi"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Kode Shift *</label>
              <input
                type="text"
                name="code"
                value={formData.code}
                onChange={(e) => setFormData((prev) => ({ ...prev, code: e.target.value.toUpperCase() }))}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent uppercase"
                placeholder="PG"
                required
                maxLength={10}
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Jam Mulai *</label>
              <input
                type="time"
                name="start_time"
                value={formData.start_time}
                onChange={handleChange}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Jam Selesai *</label>
              <input
                type="time"
                name="end_time"
                value={formData.end_time}
                onChange={handleChange}
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-gray-700 mb-2">Toleransi Telat (menit)</label>
              <input
                type="number"
                name="late_tolerance"
                value={formData.late_tolerance}
                onChange={handleChange}
                min="0"
                max="240"
                className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent"
              />
            </div>

            <div className="flex items-center gap-6 pt-7">
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  name="is_overnight"
                  checked={formData.is_overnight}
                  onChange={handleChange}
                  className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                Overnight
              </label>
              <label className="flex items-center gap-2 text-sm text-gray-700">
                <input
                  type="checkbox"
                  name="is_active"
                  checked={formData.is_active}
                  onChange={handleChange}
                  className="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                />
                Aktif
              </label>
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium text-gray-700 mb-2">Keterangan</label>
            <textarea
              name="description"
              value={formData.description}
              onChange={handleChange}
              rows={3}
              className="w-full rounded-lg border border-gray-300 px-4 py-2 focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
              placeholder="Opsional, contoh: shift operasional harian."
            />
          </div>

          <div className="bg-blue-50 border border-blue-100 rounded-lg p-3 text-xs text-blue-700">
            Untuk shift malam yang melewati tengah malam, aktifkan opsi <strong>Overnight</strong> agar jam selesai boleh lebih kecil dari jam mulai.
          </div>

          <div className="flex justify-end gap-3 pt-4 border-t border-gray-200">
            <button
              type="button"
              onClick={onClose}
              className="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50"
            >
              Batal
            </button>
            <button
              type="submit"
              disabled={loading}
              className="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {loading ? 'Menyimpan...' : shift ? 'Perbarui' : 'Simpan'}
            </button>
          </div>
        </form>
      </div>
    </div>
  )
}

const formatTimeInput = (time) => {
  if (!time) return ''
  return String(time).substring(0, 5)
}
