import styles from '@/styles/components/Modal.module.scss';

interface ModalProps {
  message: string;
  onClose: () => void;
}

const Modal: React.FC<ModalProps> = ({ message, onClose }) => {
  return (
    <div className={styles.blockModal} onClick={onClose}>
      <div>
        <p>{message}</p>
        <button onClick={onClose}>閉じる</button>
      </div>
    </div>
  );
};

export default Modal;
