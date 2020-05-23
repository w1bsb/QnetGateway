/*
 *   Copyright (C) 2020 by Thomas Early N7TAE
 *
 *   This program is free software; you can redistribute it and/or modify
 *   it under the terms of the GNU General Public License as published by
 *   the Free Software Foundation; either version 2 of the License, or
 *   (at your option) any later version.
 *
 *   This program is distributed in the hope that it will be useful,
 *   but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   GNU General Public License for more details.
 *
 *   You should have received a copy of the GNU General Public License
 *   along with this program; if not, write to the Free Software
 *   Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 */

#include <iostream>
#include <unistd.h>
#include <sys/un.h>
#include <sys/socket.h>

#include "UnixPacketSock.h"

CUnixPacket::CUnixPacket() : m_fd(-1) {}

ssize_t CUnixPacket::Read(void *data, const ssize_t size)
{
	return read(m_fd, data, size);
}

bool CUnixPacket::Write(const void *data, const ssize_t size) const
{
	ssize_t written = write(m_fd, data, size);
	if (written != size) {
		std::cout << "CUnixPacketServer::Write ERROR: only wrote " << written << " of " << size << " bytes" << std::endl;
		return true;
	}
	return false;
}

int CUnixPacket::GetFD()
{
	return m_fd;
}

CUnixPacketServer::CUnixPacketServer() : m_server(-1) {}

CUnixPacketServer::~CUnixPacketServer()
{
	Close();
}

bool CUnixPacketServer::Open(const char *name)
{
	m_server = socket(AF_UNIX, SOCK_SEQPACKET, 0);
	if (m_server < 0) {
		std::cerr << "Cannot open " << name << " Unix server socket!" << std::endl;
		return true;
	}

	struct sockaddr_un addr;
	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	memcpy(addr.sun_path+1, name, strlen(name));
	if (-1 == bind(m_server, (struct sockaddr *)&addr, sizeof(addr))) {
		std::cerr << "Cannot bind " << name << "Unix server socket!" << std::endl;
		Close();
		return true;
	}

	if (-1 == listen(m_server, 1)) {
		std::cerr << "Cannot listen on " << name << "Unix server socket!" << std::endl;
		Close();
		return true;
	}

	m_fd = accept(m_server, nullptr, 0);
	if (m_fd < 0) {
		std::cerr << "Cannot accept on " << name << "Unix server socket!" << std::endl;
		Close();
		return true;
	}
	return false;
}

void CUnixPacketServer::Close()
{
	if (m_server >= 0) {
		close(m_server);
		m_server = -1;
	}
	if (m_fd >= 0) {
		close(m_fd);
		m_fd = -1;
	}
}

CUnixPacketClient::~CUnixPacketClient()
{
	Close();
}

bool CUnixPacketClient::Open(const char *name)
{
	m_fd = socket(AF_UNIX, SOCK_SEQPACKET, 0);
	if (m_fd < 0) {
		std::cerr << "Cannot open " << name << " Unix client socket!" << std::endl;
		return true;
	}

	struct sockaddr_un addr;
	memset(&addr, 0, sizeof(addr));
	addr.sun_family = AF_UNIX;
	memcpy(addr.sun_path+1, name, strlen(name));
	if (-1 == connect(m_fd, (struct sockaddr *)&addr, sizeof(addr))) {
		std::cerr << "Cannot connect " << name << "Unix client socket!" << std::endl;
		Close();
		return true;
	}

	return false;
}

void CUnixPacketClient::Close()
{
	if (m_fd >= 0) {
		close(m_fd);
		m_fd = -1;
	}
}
