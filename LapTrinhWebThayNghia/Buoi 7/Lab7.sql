-- //cau1
CREATE TABLE khachhang (
    email VARCHAR(50) PRIMARY KEY,
    matkhau VARCHAR(32),
    tenkh VARCHAR(50),
    diachi VARCHAR(100),
    dienthoai VARCHAR(11)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE hoadon (
    mahd INT PRIMARY KEY,
    email VARCHAR(50),
    ngayhd DATETIME,
    tennugoinhan VARCHAR(50),
    diachinguoinhan VARCHAR(80),
    ngaynhan DATE,
    dienthoainguoinhan VARCHAR(10),
    trangthai TINYINT,
    FOREIGN KEY (email) REFERENCES khachhang(email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE loai (
    maloai VARCHAR(5) PRIMARY KEY,
    tenloai VARCHAR(50)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE nhaxb (
    manxb VARCHAR(5) PRIMARY KEY,
    tennxb TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE sach (
    masach VARCHAR(20) PRIMARY KEY,
    manxb VARCHAR(5),
    maloai VARCHAR(5),
    tensach VARCHAR(250),
    mota TEXT,
    gia DECIMAL(10,2),
    hinh VARCHAR(50),
    FOREIGN KEY (manxb) REFERENCES nhaxb(manxb),
    FOREIGN KEY (maloai) REFERENCES loai(maloai)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chitiethd (
    mahd INT,
    masach VARCHAR(20),
    soluong TINYINT,
    gia DECIMAL(10,2),
    PRIMARY KEY (mahd, masach),
    FOREIGN KEY (mahd) REFERENCES hoadon(mahd),
    FOREIGN KEY (masach) REFERENCES sach(masach)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- //cau2
alter table hoadon
modify trangthai tinyint default 0;



-- //cau 3
CREATE VIEW Muoi_Cuon_Sach_Co_Gia_Cao_Nhat AS
SELECT *
FROM sach
ORDER BY gia DESC
LIMIT 10;


-- //cau4
CREATE PROCEDURE LietKeSachTheoLoai(IN p_maloai VARCHAR(5))
BEGIN
    SELECT masach, tensach
    FROM sach
    WHERE maloai = p_maloai;
END


-- //Nang Cao
-- //5.1
SELECT s.masach, s.tensach, s.maloai, l.tenloai
FROM sach s
JOIN loai l ON s.maloai = l.maloai
ORDER BY l.tenloai, s.tensach;


-- //5.2
CREATE PROCEDURE capnhat_gia_sach (
    IN p_masach VARCHAR(20),
    IN p_gia DECIMAL(10,2)
)
BEGIN
    UPDATE sach
    SET gia = p_gia
    WHERE masach = p_masach;
END 


-- //5.3
SELECT s.masach, s.tensach, SUM(ct.soluong) AS tong_soluong
FROM chitiethd ct
JOIN sach s ON ct.masach = s.masach
GROUP BY s.masach, s.tensach
ORDER BY tong_soluong DESC
LIMIT 1;


-- //5.4
CREATE VIEW top10_sach_banchay AS
SELECT s.masach, s.tensach, SUM(ct.soluong) AS tong_soluong
FROM chitiethd ct
JOIN sach s ON ct.masach = s.masach
GROUP BY s.masach, s.tensach
ORDER BY tong_soluong DESC
LIMIT 10;


-- //5.5
mysqldump -u root -p bookstore > bookstore_backup.sql


-- //5.6
DROP DATABASE IF EXISTS bookstore;


-- //5.7
mysql -u root -p bookstore < bookstore_backup.sql


