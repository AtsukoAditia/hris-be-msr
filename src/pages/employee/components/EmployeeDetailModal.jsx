import { BadgeCheck, Briefcase, Calendar, Camera, Hash, Mail, MapPin, Phone, Shield, User, Users, X } from 'lucide-react'
import { useRef, useState } from 'react'
import employeeService from '../../../services/employeeService'

const EmployeeDetailModal = ({ employee, onClose, onFaceUpdated }) => {
  const [isUploadingFace, setIsUploadingFace] = useState(false)
  const [facePreview, setFacePreview] = useState(employee?.face_image_url || null)
  const [faceError, setFaceError] = useState(null)
  const fileInputRef = useRef(null)

  if (!employee) return null

  const handleFaceFileChange = async (event) => {
    const file = event.target.files?.[0]
    if (!file) return

    setFaceError(null)
    setFacePreview(URL.createObjectURL(file))
    setIsUploadingFace(true)

    try {
      const res = await employeeService.enrollFace(employee.id, file)
      onFaceUpdated?.(res.data?.data)
    } catch (err) {
      console.error(err)
      setFaceError(err.response?.data?.message || 'Gagal menyimpan foto wajah.')
    } finally {
      setIsUploadingFace(false)
      event.target.value = ''
    }
  }

  return (
    <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
      <div className="bg-white rounded-xl shadow-xl max-w-4xl w-full max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between p-6 border-b border-gray-200">
          <h2 className="text-xl font-semibold text-gray-900">Detail Pegawai</h2>
          <button type="button" onClick={onClose} className="p-2 text-gray-400 hover:text-gray-600 transition-colors">
            <X className="w-5 h-5" />
          </button>
        </div>

        <div className="p-6">
          <div className="flex items-center mb-6">
            <div className="w-20 h-20 bg-blue-100 rounded-full flex items-center justify-center mr-4 overflow-hidden">
              {facePreview ? (
                <img src={facePreview} alt="Face enrollment" className="w-full h-full object-cover" />
              ) : (
                <User className="w-10 h-10 text-blue-600" />
              )}
            </div>
            <div>
              <h3 className="text-2xl font-bold text-gray-900">{employee.name || '-'}</h3>
              <p className="text-gray-600">{employee.position || '-'}</p>
              <div className="flex flex-wrap gap-2 mt-2">
                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${employee.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`}>
                  {employee.status === 'active' ? 'Aktif' : 'Nonaktif'}
                </span>
                <span className="inline-flex px-2 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 capitalize">
                  {employee.role || '-'}
                </span>
                <span className={`inline-flex px-2 py-1 text-xs font-medium rounded-full ${employee.is_face_registered ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'}`}>
                  {employee.is_face_registered ? 'Face registered' : 'Face belum terdaftar'}
                </span>
              </div>
            </div>
          </div>

          <div className="mb-6 rounded-xl border border-gray-200 p-4 bg-gray-50">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div>
                <h3 className="text-sm font-semibold text-gray-900">Foto Wajah Absensi</h3>
                <p className="text-xs text-gray-500 mt-1">
                  Siapkan foto wajah referensi untuk validasi absensi kamera nanti. Format JPG, PNG, atau WEBP maksimal 2MB.
                </p>
                <p className="text-xs text-gray-500 mt-1">
                  Terdaftar: {employee.face_registered_at ? formatDate(employee.face_registered_at) : '-'}
                </p>
                {faceError && <p className="text-xs text-red-600 mt-2">{faceError}</p>}
              </div>
              <div>
                <input
                  ref={fileInputRef}
                  type="file"
                  accept="image/*"
                  capture="user"
                  className="hidden"
                  onChange={handleFaceFileChange}
                />
                <button
                  type="button"
                  disabled={isUploadingFace}
                  onClick={() => fileInputRef.current?.click()}
                  className="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors disabled:opacity-50"
                >
                  <Camera className="w-4 h-4 mr-2" />
                  {isUploadingFace ? 'Menyimpan...' : 'Capture / Upload Wajah'}
                </button>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div className="space-y-4">
              <InfoItem icon={<Mail />} label="Email" value={employee.email} />
              <InfoItem icon={<Hash />} label="NIK" value={employee.nik} />
              <InfoItem icon={<Phone />} label="Telepon" value={employee.phone} />
              <InfoItem icon={<Calendar />} label="Tanggal Lahir" value={employee.birth_date} />
              <InfoItem icon={<Users />} label="Gender" value={formatGender(employee.gender)} />
              <InfoItem icon={<MapPin />} label="Alamat" value={employee.address} />
            </div>

            <div className="space-y-4">
              <InfoItem icon={<Briefcase />} label="Departemen" value={employee.department} />
              <InfoItem icon={<BadgeCheck />} label="Role" value={employee.role} capitalize />
              <InfoItem icon={<Calendar />} label="Tanggal Bergabung" value={employee.join_date} />
              <InfoItem icon={<Briefcase />} label="Tipe Kerja" value={formatEmploymentType(employee.employment_type)} />
              <InfoItem icon={<Shield />} label="No. Pegawai" value={employee.formatted_employee_number || employee.employee_number} />
            </div>
          </div>

          <div className="flex justify-end pt-6 mt-6 border-t border-gray-200">
            <button type="button" onClick={onClose} className="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
              Tutup
            </button>
          </div>
        </div>
      </div>
    </div>
  )
}

const InfoItem = ({ icon, label, value, capitalize = false }) => (
  <div className="flex items-start">
    <span className="w-5 h-5 text-gray-400 mr-3 mt-0.5 [&>svg]:w-5 [&>svg]:h-5">{icon}</span>
    <div>
      <p className="text-sm text-gray-500">{label}</p>
      <p className={`text-gray-900 ${capitalize ? 'capitalize' : ''}`}>{value || '-'}</p>
    </div>
  </div>
)

const formatDate = (value) => {
  if (!value) return '-'
  return new Date(value).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
}

const formatGender = (value) => {
  const map = {
    male: 'Laki-laki',
    female: 'Perempuan',
  }

  return map[value] || value || '-'
}

const formatEmploymentType = (value) => {
  const map = {
    permanent: 'Permanent',
    contract: 'Contract',
    internship: 'Internship',
  }

  return map[value] || value || '-'
}

export default EmployeeDetailModal
