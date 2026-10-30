<template>
  <div class="container mt-4">
    <!-- หัวข้อหน้า -->
    <h2 class="mb-3">รายการติดต่อ</h2>
    
    <!-- ตารางแสดงข้อมูล Contacts -->
    <div class="text-end mb-3">
      <button class="btn btn-primary" @click="openAddModal">Add+</button>
    </div>

    <table class="table table-bordered table-striped">
      <thead class="table-dark">
        <tr>
          <th>ID</th>

          <th>หัวข้อ</th>
          <th>รายละเอียด</th>
          <th>อีเมล</th>
          <th>วันที่สร้าง</th>
          <th>แก้ไข/ลบ</th>
        </tr>
      </thead>

      <tbody>
        <tr v-for="(item,index) in contacts" :key="item.contact_id">
          <td>{{ index + 1 }}</td>       <!-- แสดงลำดับที่ (เริ่มจาก 1) -->

          <td>{{ item.subject }}</td>
          <td>{{ item.detail }}</td>
          <td>{{ item.email }}</td>
          <td>{{ item.created_at }}</td>
          <td>
            <button class="btn btn-warning btn-sm me-2" @click="openEditModal(item)">
              แก้ไข
            </button>
            <button class="btn btn-danger btn-sm" @click="deleteContact(item.contact_id)">
              ลบ
            </button>
          </td>
        </tr>
      </tbody>
    </table>

    <!-- Loading: แสดงระหว่างรอข้อมูล -->
    <div v-if="loading" class="text-center">
      <p>กำลังโหลดข้อมูล...</p>
    </div>

    <!-- Error: แสดงเมื่อเกิดข้อผิดพลาด -->
    <div v-if="error" class="alert alert-danger">
      {{ error }}
    </div>

    <div class="modal fade" id="contactModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">{{ isEditMode ? "แก้ไขข้อมูลติดต่อ" : "เพิ่มข้อมูลติดต่อ" }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <form @submit.prevent="saveContact">
              <div class="mb-3">
                <label class="form-label">หัวข้อ</label>
                <input v-model="contactForm.subject" type="text" class="form-control" required>
              </div>
              <div class="mb-3">
                <label class="form-label">รายละเอียด</label>
                <textarea v-model="contactForm.detail" class="form-control" rows="4" required></textarea>
              </div>
              <div class="mb-3">
                <label class="form-label">อีเมล</label>
                <input v-model="contactForm.email" type="email" class="form-control" required>
              </div>
              <button type="submit" class="btn btn-success">
                {{ isEditMode ? "บันทึกการแก้ไข" : "เพิ่มข้อมูลติดต่อ" }}
              </button>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>


<script>
import { ref, onMounted } from "vue";

export default {
  name: "ContactList",
  setup() {
    const contacts = ref([]);
    const loading = ref(true);
    const error = ref(null);
    const contactForm = ref({ subject: "", detail: "", email: "" });
    const isEditMode = ref(false);
    let contactModal = null;

    const fetchContacts = async () => {
      try {
        const response = await fetch("http://localhost/week7/php_api/show_contacts.php");
        const result = await response.json();

        if (!response.ok) {
          throw new Error(result.message || "ไม่สามารถดึงข้อมูลติดต่อได้");
        }

        if (result.success) {
          contacts.value = result.data;
        } else {
          throw new Error(result.message || "ไม่สามารถดึงข้อมูลติดต่อได้");
        }
      } catch (err) {
        error.value = err.message;
      } finally {
        loading.value = false;
      }
    };

    onMounted(() => {
      fetchContacts();
      const modalElement = document.getElementById("contactModal");
      if (modalElement && window.bootstrap) {
        contactModal = new window.bootstrap.Modal(modalElement);
      }
    });

    const openAddModal = () => {
      isEditMode.value = false;
      contactForm.value = { subject: "", detail: "", email: "" };
      contactModal?.show();
    };

    const openEditModal = (contact) => {
      isEditMode.value = true;
      contactForm.value = {
        contact_id: contact.contact_id,
        subject: contact.subject,
        detail: contact.detail,
        email: contact.email
      };
      contactModal?.show();
    };

    const saveContact = async () => {
      try {
        const response = await fetch("http://localhost/week7/php_api/show_contacts.php", {
          method: isEditMode.value ? "PUT" : "POST",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify(contactForm.value)
        });
        const result = await response.json();

        if (!response.ok || !result.success) {
          throw new Error(result.message || "ไม่สามารถบันทึกข้อมูลติดต่อได้");
        }

        alert(result.message);
        contactModal?.hide();
        await fetchContacts();
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      }
    };

    const deleteContact = async (id) => {
      if (!confirm("คุณต้องการลบข้อมูลติดต่อนี้ใช่หรือไม่?")) return;

      try {
        const response = await fetch("http://localhost/week7/php_api/show_contacts.php", {
          method: "DELETE",
          headers: { "Content-Type": "application/json" },
          body: JSON.stringify({ contact_id: id })
        });
        const result = await response.json();

        if (result.success) {
          contacts.value = contacts.value.filter(
            (contact) => String(contact.contact_id) !== String(id)
          );
          alert(result.message);
        } else {
          throw new Error(result.message || "ไม่สามารถลบข้อมูลติดต่อได้");
        }
      } catch (err) {
        alert("เกิดข้อผิดพลาด: " + err.message);
      }
    };

    return {
      contacts,
      loading,
      error,
      contactForm,
      isEditMode,
      openAddModal,
      openEditModal,
      saveContact,
      deleteContact
    };
  }
};
</script>
